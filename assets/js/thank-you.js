(() => {
"use strict";
const TransitionText = (element, has_icon = false) => {
  return {
    classes: [],
    has_icon: false,
    original_value: element.innerHTML,
    get_icon() {
      return this.has_icon ? '<span class="wu-spin wu-inline-block wu-mr-2"><span class="dashicons-wu-loader"></span></span>' : "";
    },
    clear_classes() {
      element.classList.remove(...this.classes);
    },
    add_classes(classes) {
      this.classes = classes;
      element.classList.add(...classes);
    },
    text(text, classes, toggle_icon = false) {
      this.clear_classes();
      if (toggle_icon) {
        this.has_icon = !this.has_icon;
      }
      element.animate([
        {
          opacity: "1"
        },
        {
          opacity: "0.75"
        }
      ], {
        duration: 300,
        iterations: 1
      });
      setTimeout(() => {
        this.add_classes(classes ?? []);
        element.innerHTML = this.get_icon() + text;
        element.style.opacity = "0.75";
      }, 300);
      return this;
    },
    done(timeout = 5e3) {
      setTimeout(() => {
        element.animate([
          {
            opacity: "0.75"
          },
          {
            opacity: "1"
          }
        ], {
          duration: 300,
          iterations: 1
        });
        setTimeout(() => {
          this.clear_classes();
          element.innerHTML = this.original_value;
          element.style.opacity = "1";
        }, 300);
      }, timeout);
      return this;
    }
  };
};
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".wu-resend-verification-email").forEach((element) => element.addEventListener("click", async (event) => {
    event.preventDefault();
    const transitional_text = TransitionText(element, true).text(wu_thank_you.i18n.resending_verification_email, ["wu-text-gray-400"]);
    const request = await fetch(
      wu_thank_you.ajaxurl,
      {
        method: "POST",
        headers: {
         "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
            action: "wu_resend_verification_email",
            _ajax_nonce: wu_thank_you.resend_verification_email_nonce
        }),
      }
    );
    const response = await request.json();
    if (response.success) {
      transitional_text.text(wu_thank_you.i18n.email_sent, ["wu-text-green-700"], true).done();
    } else {
      transitional_text.text(response.data[0].message, ["wu-text-red-600"], true).done();
    }
  }));
  if (!document.getElementById("wu-sites")) {
    return;
  }
  const { Vue, defineComponent } = window.wu_vue;
  window.wu_sites = new Vue(defineComponent({
    el: "#wu-sites",
    data() {
      return {
        creating: wu_thank_you.creating,
        next_queue: parseInt(wu_thank_you.next_queue, 10) + 5,
        random: 0,
        progress_in_seconds: 0,
        stopped_count: 0,
        running_count: 0,
        site_ready: false,
        // True when the URL already carries a _t= cache-bust param added by a
        // prior completion redirect.  Used to prevent any second full-page
        // navigation and break the infinite-reload loop that occurred when the
        // "stopped" branch fired window.location.reload() on every page load
        // after site creation had already finished.
        is_post_redirect: new URLSearchParams(window.location.search).has("_t"),
        // One-time guard for the Case 1 "stopped" reload.  Multiple
        // check_site_created() callbacks can be queued before the first reload
        // fires (async polling overlap), so we set this flag before calling
        // window.location.reload() and bail out of subsequent calls.
        _reload_done: false
      };
    },
    computed: {
      progress() {
        return Math.round(this.progress_in_seconds / this.next_queue * 100);
      }
    },
    mounted() {
      /*
       * Kick wp-cron immediately to start the async site creation ASAP.
       *
       * Always start polling regardless of has_pending_site. The page may
       * load before the pending site is created (during Stripe webhook
       * delay). Without polling, the user stares at "Creating" forever
       * even after the site is ready.
       *
       * @since 2.4.13
       */
      fetch("/wp-cron.php?doing_wp_cron");
      this.check_site_created();
    },
    methods: {
      async check_site_created() {
        const url = new URL(wu_thank_you.ajaxurl);
        url.searchParams.set("action", "wu_check_pending_site_created");
        url.searchParams.set("membership_hash", wu_thank_you.membership_hash);
        let response;
        try {
          response = await fetch(url).then((request) => request.json());
        } catch (e) {
          // Network error or non-JSON response -- retry in 3s without stopping.
          this.stopped_count++;
          setTimeout(this.check_site_created, 3000);
          return;
        }
        if (response.publish_status === "completed") {
          this.creating = false;
          this.site_ready = true;
          // Cache-bust redirect only when we actually watched the site transition
          // through "running" during THIS page load, AND we have not already done
          // a redirect (is_post_redirect).  Both guards are required:
          //  • running_count === 0 → gateway completed before page loaded; the PHP
          //    template already has the correct state, no reload needed.
          //  • is_post_redirect === true → we already navigated once; a second
          //    redirect would start an infinite loop (completed page → redirect →
          //    completed again → redirect …).
          if (this.running_count > 0 && !this.is_post_redirect) {
            setTimeout(() => {
              var sep = window.location.href.indexOf("?") > -1 ? "&" : "?";
              window.location.href = window.location.href.split("#")[0] + sep + "_t=" + Date.now();
            }, 1500);
          }
        } else if (response.publish_status === "running") {
          this.creating = true;
          this.stopped_count = 0;
          this.running_count++;
          // Kick cron every 3 polls to keep Action Scheduler active during site creation.
          if (this.running_count % 3 === 0) {
            fetch("/wp-cron.php?doing_wp_cron");
          }
          if (this.running_count > 60) {
            fetch("/wp-cron.php?doing_wp_cron");
            if (!this.is_post_redirect) {
              // Use the _t cache-bust redirect (not location.reload) so the next
              // page load sets is_post_redirect = true, preventing a second redirect
              // if creation is still running on that page.
              setTimeout(() => {
                var sep = window.location.href.indexOf("?") > -1 ? "&" : "?";
                window.location.href = window.location.href.split("#")[0] + sep + "_t=" + Date.now();
              }, 3e3);
            } else {
              // Already did one redirect this cycle; slow-poll instead of looping.
              setTimeout(this.check_site_created, 5000);
            }
          } else {
            // Adaptive polling: 1.5s for first 30s, then 3s
            var wait = this.running_count < 20 ? 1500 : 3000;
            setTimeout(this.check_site_created, wait);
          }
        } else {
          // status === "stopped": async job not started yet OR site already created.
          //
          // NEVER call window.location.reload() unconditionally here — doing so
          // causes an infinite refresh loop: a finished site always returns "stopped"
          // (the job is no longer running), so every page load would cycle through
          // 3 stopped polls → reload → 3 stopped polls → reload endlessly.
          //
          // Intent-aware strategy:
          //  1. running_count > 0  → we watched creation run this session but it
          //     stopped unexpectedly; do one location.reload() for fresh server state.
          //     The reloaded page will have running_count = 0, so this branch can
          //     only fire once before falling through to case 2 or 3.
          //  2. wu_thank_you.creating === false (PHP already reports site done) →
          //     mark ready and stop polling; no navigation needed.
          //  3. wu_thank_you.creating === true but never saw "running" (webhook
          //     delay, job not started yet) → keep slow-polling; do NOT reload.
          this.creating = false;
          this.stopped_count++;
          if (this.stopped_count >= 3) {
            if (this.running_count > 0 && !this._reload_done) {
              // Case 1: was running this session, now stopped — one-time reload.
              // _reload_done guards against multiple queued callbacks all firing
              // reload before the first navigation clears the page.
              this._reload_done = true;
              window.location.reload();
            } else if (!wu_thank_you.creating) {
              // Case 2: PHP already reported creation complete — mark ready.
              this.site_ready = true;
            } else {
              // Case 3: still waiting for job to start — keep slow-polling.
              setTimeout(this.check_site_created, 3e3);
            }
          } else {
            setTimeout(this.check_site_created, 3e3);
          }
        }
      }
    }
  }));
});
})()