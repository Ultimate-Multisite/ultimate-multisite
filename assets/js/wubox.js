(() => {
"use strict";
const createImageBox = (boxWindow, loaded, url, caption, imageGroup) => {
  let prevCaption = "", prevURL = "", prevHTML = "", nextCaption = "", nextURL = "", nextHTML = "", imageCount = "", foundURL = false;
  if (imageGroup) {
    const tempArray = document.querySelectorAll(`a[rel="${imageGroup}"]`);
    for (let index = 0; index < tempArray.length && nextHTML === ""; index++) {
      if (tempArray[index].href !== url) {
        if (foundURL) {
          nextCaption = tempArray[index].title;
          nextURL = tempArray[index].href;
          nextHTML = "<span id='WUB_next'>&nbsp;&nbsp;<a href='#'>" + wuboxL10n.next + "</a></span>";
        } else {
          prevCaption = tempArray[index].title;
          prevURL = tempArray[index].href;
          prevHTML = "<span id='WUB_prev'>&nbsp;&nbsp;<a href='#'>" + wuboxL10n.prev + "</a></span>";
        }
      } else {
        foundURL = true;
        imageCount = wuboxL10n.image + " " + (index + 1) + " " + wuboxL10n.of + " " + tempArray.length;
      }
    }
  }
  const imgPreloader = new Image();
  imgPreloader.onload = () => {
    var _a, _b, _c, _d;
    imgPreloader.onload = null;
    const pagesize = getPageSize();
    const x = pagesize.width - 150;
    const y = pagesize.height - 150;
    let imageWidth = imgPreloader.width;
    let imageHeight = imgPreloader.height;
    if (imageWidth > x) {
      imageHeight = imageHeight * (x / imageWidth);
      imageWidth = x;
      if (imageHeight > y) {
        imageWidth = imageWidth * (y / imageHeight);
        imageHeight = y;
      }
    } else if (imageHeight > y) {
      imageWidth = imageWidth * (y / imageHeight);
      imageHeight = y;
      if (imageWidth > x) {
        imageHeight = imageHeight * (x / imageWidth);
        imageWidth = x;
      }
    }
    setBoxPosition(boxWindow, imageWidth, imageHeight);
    boxWindow.insertAdjacentHTML("beforeend", `
      <a href='' id='WUB_ImageOff'>
        <span class='screen-reader-text'>${wuboxL10n.close}</span>
        <img id='WUB_Image' src='${url}' width='${imageWidth}' height='${imageHeight}' alt='${caption}'/>
      </a>
      <div id='WUB_caption'>
        ${caption}
        <div id='WUB_secondLine'>
          ${imageCount + prevHTML + nextHTML}
        </div>
      </div>
      <div id='WUB_closeWindow'>
        <button type='button' id='WUB_closeWindowButton'>
          <span class='screen-reader-text'>${wuboxL10n.close}</span>
          <span class='wutb-close-icon'></span>
        </button>
      </div>
    `);
    (_a = document.getElementById("WUB_closeWindowButton")) == null ? void 0 : _a.addEventListener("click", removeBox);
    const goPrev = () => {
      boxWindow.innerHTML = "";
      unloadKeydownEvent();
      showBox(prevCaption, prevURL, imageGroup);
    };
    const goNext = () => {
      boxWindow.innerHTML = "";
      unloadKeydownEvent();
      showBox(nextCaption, nextURL, imageGroup);
    };
    (_b = document.getElementById("WUB_prev")) == null ? void 0 : _b.addEventListener("click", goPrev);
    (_c = document.getElementById("WUB_next")) == null ? void 0 : _c.addEventListener("click", goNext);
    const keydownEvent = (e) => {
      if (e.key === "Escape") {
        removeBox();
      } else if (e.key === "ArrowRight" && nextHTML) {
        goNext();
      } else if (e.key === "ArrowLeft" && prevHTML) {
        goPrev();
      }
    };
    const unloadKeydownEvent = () => {
      window.removeEventListener("keydown", keydownEvent);
      document.body.removeEventListener("wubox:unload", unloadKeydownEvent);
    };
    window.addEventListener("keydown", keydownEvent);
    document.body.addEventListener("wubox:unload", unloadKeydownEvent);
    (_d = document.getElementById("WUB_ImageOff")) == null ? void 0 : _d.addEventListener("click", removeBox);
    loaded();
  };
  imgPreloader.src = url;
};
const createIframeBox = (boxWindow, boxOverlay, loaded, url, caption, params) => {
  var _a, _b, _c;
  const urlNoQuery = url.split("WUB_");
  (_a = document.getElementById("WUB_load")) == null ? void 0 : _a.remove();
  if (params.modal) {
    boxOverlay.removeEventListener("click", removeBox);
    boxWindow.insertAdjacentHTML("beforeend", `
      <iframe
        id='WUB_iframeContent'
        frameborder='0'
        hspace='0'
        allowtransparency='true'
        src='${urlNoQuery[0]}'
        name='WUB_iframeContent${Math.round(Math.random() * 1e3)}'
        style='width:${params.width + 29}px; height:${params.height + 17}px;'>${wuboxL10n.noiframes}
      </iframe>`);
    (_b = document.getElementById("WUB_iframeContent")) == null ? void 0 : _b.addEventListener("load", showBoxIframe);
  } else {
    boxWindow.insertAdjacentHTML("beforeend", `
      <div id='WUB_title'>
        <div id='WUB_ajaxWindowTitle'>${caption}</div>
        <div id='WUB_closeAjaxWindow'>
          <button type='button' id='WUB_closeWindowButton'>
            <span class='screen-reader-text'>${wuboxL10n.close}</span><span class='wutb-close-icon'></span>
          </button>
        </div>
      </div>
      <iframe
        id='WUB_iframeContent'
        frameborder='0'
        hspace='0'
        allowtransparency='true'
        src='${urlNoQuery[0]}'
        name='WUB_iframeContent${Math.round(Math.random() * 1e3)}'
        style='width:${params.width + 29}px; height:${params.height + 17}px;'
      >
        ${wuboxL10n.noiframes}
      </iframe>`);
    (_c = document.getElementById("WUB_iframeContent")) == null ? void 0 : _c.addEventListener("load", showBoxIframe);
  }
  setBoxPosition(boxWindow, params.width, params.height);
  loaded();
};
const baseAjaxElement = (boxWindow, boxOverlay, caption, params) => {
  if (boxWindow.style.visibility !== "visible") {
    if (params.modal) {
      boxOverlay.removeEventListener("click", removeBox);
      boxWindow.insertAdjacentHTML("beforeend", `<div id='WUB_ajaxContent' class='WUB_modal' style='width:${params.width}px; height:${params.height}px;'></div>`);
    } else {
      boxWindow.insertAdjacentHTML("beforeend", `
        <div id='WUB_title'>
          <div id='WUB_ajaxWindowTitle'>${caption}</div>
          <div id='WUB_closeAjaxWindow'>
            <button type='button' id='WUB_closeWindowButton'>
              <span class='screen-reader-text'>${wuboxL10n.close}</span>
              <span class='wutb-close-icon'></span>
            </button>
          </div>
        </div>
        <div id='WUB_ajaxContent' style='width:${params.width}px; height:${params.height}px;'></div>`);
    }
  } else {
    const ajaxContent = document.getElementById("WUB_ajaxContent");
    ajaxContent.style.width = params.width + "px";
    ajaxContent.style.height = params.height + "px";
    ajaxContent.scrollTop = 0;
    ajaxContent.innerHTML = caption;
  }
  return document.getElementById("WUB_ajaxContent");
};
const createAjaxBox = (boxWindow, boxOverlay, loaded, url, caption, params) => {
  const ajaxContent = baseAjaxElement(boxWindow, boxOverlay, caption, params);
  const load_url = url + (url.includes("?") ? "&" : "?") + "random=" + (/* @__PURE__ */ new Date()).getTime();
  fetch(load_url, {
    headers: {
      "X-Requested-With": "XMLHttpRequest"
    }
  }).then((response) => response.text()).then((html) => {
    ajaxContent.innerHTML = html;
    setBoxPosition(boxWindow, params.width, params.height);
    loaded();
  });
};
const createInlineBox = (boxWindow, boxOverlay, loaded, caption, params) => {
  const ajaxContent = baseAjaxElement(boxWindow, boxOverlay, caption, params);
  const element = document.getElementById(params.inlineId);
  ajaxContent.insertAdjacentElement("beforeend", element == null ? void 0 : element.children[0]);
  const unloadAction = () => {
    element == null ? void 0 : element.insertAdjacentElement("afterbegin", ajaxContent.children[0]);
    document.body.removeEventListener("wubox:unload", unloadAction);
  };
  document.body.addEventListener("wubox:unload", unloadAction);
  setBoxPosition(boxWindow, params.width, params.height);
  loaded();
};
const showFormErrors = (form, errors) => {
  const formId = form.getAttribute("id");
  const errorApp = window["wu_" + formId + "_errors"];
  if (errorApp) {
    errorApp.errors = errors;
  }
  const formAppEl = document.querySelector('[data-wu-app="' + formId + '_errors"]');
  if (formAppEl) {
    formAppEl.setAttribute("tabindex", "-1");
    formAppEl.focus();
  }
};
const formSubmit = (form) => async (event) => {
  event.preventDefault();
  const textArea = form.querySelector("textarea[data-editor]");
  const textAreaInput = textArea ? form.querySelector('input[name="' + textArea.id + '"]') : null;
  if (textArea && textAreaInput) {
    textAreaInput.value = textArea.value;
  }
  const blocked_form = wu_block_ui(form);
  if (window["wu_" + form.getAttribute("id") + "_errors"]) {
    window["wu_" + form.getAttribute("id") + "_errors"].errors = [];
  }
  const submitButton = event.submitter.value;
  const formData = new FormData(form);
  formData.append("submit", submitButton);
  let response;
  try {
    const fetchResponse = await fetch(form.getAttribute("action"), {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    });
    const txt = await fetchResponse.text();
    if (!fetchResponse.ok) {
      throw new Error(fetchResponse.status + " " + fetchResponse.statusText);
    }
    response = txt ? JSON.parse(txt) : null;
  } catch (error) {
    blocked_form.unblock();
    const message = (typeof wuboxL10n !== "undefined" && wuboxL10n.server_error) ? wuboxL10n.server_error : "An unexpected error occurred. Please try again or contact support if the problem persists.";
    showFormErrors(form, [{ code: "server-error", message }]);
    return;
  }
  if (response === null || response.data === null) {
    blocked_form.unblock();
    removeBox();
    return;
  }
  if (!response.success) {
    blocked_form.unblock();
    showFormErrors(form, response.data);
  }
  if (typeof response.data.tables === "object") {
    blocked_form.unblock();
    removeBox();
    Object.keys(response.data.tables).forEach((key) => {
      window[key].update();
    });
  }
  if (typeof response.data.download_url === "string") {
    blocked_form.unblock();
    removeBox();
    // Trigger the ZIP download without navigating away from the current page.
    const a = document.createElement("a");
    a.href = response.data.download_url;
    a.setAttribute("download", "");
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }
  if (typeof response.data.redirect_url === "string") {
    window.location.href = response.data.redirect_url;
  }
  if (typeof response.data.send === "object") {
    window[response.data.send.scope][response.data.send.function_name](response.data.send.data, removeBox);
  }
};
function getPageSize() {
  const de = document.documentElement;
  const width = window.innerWidth || self.innerWidth || de && de.clientWidth || document.body.clientWidth;
  const height = window.innerHeight || self.innerHeight || de && de.clientHeight || document.body.clientHeight;
  return { width, height };
}
function setBoxPosition(boxWindow, width, height) {
  boxWindow.style.marginLeft = "-" + width / 2 + "px";
  boxWindow.style.marginTop = "-" + height / 2 + "px";
}
function fadeOutEffect(element, duration, callback) {
  const startOpacity = parseFloat(getComputedStyle(element).opacity);
  let startTime = null;
  function step(timestamp) {
    if (!startTime)
      startTime = timestamp;
    const progress = timestamp - startTime;
    const opacity = Math.max(startOpacity - progress / duration, 0);
    element.style.opacity = opacity.toString();
    if (progress < duration) {
      requestAnimationFrame(step);
    } else {
      element.style.display = "none";
      if (callback)
        callback();
    }
  }
  requestAnimationFrame(step);
}
function showBoxIframe() {
  var _a;
  (_a = document.getElementById("WUB_load")) == null ? void 0 : _a.remove();
  const boxWindow = document.getElementById("WUB_window");
  boxWindow.style.visibility = "visible";
  document.body.dispatchEvent(new Event("wubox:iframe:loaded"));
}
function showBox(caption, url, imageGroup) {
  var _a;
  const boxOverlay = document.getElementById("WUB_overlay") || document.createElement("div");
  boxOverlay.id = "WUB_overlay";
  const boxWindow = document.getElementById("WUB_window") || document.createElement("div");
  boxWindow.id = "WUB_window";
  document.body.insertAdjacentElement("beforeend", boxOverlay);
  document.body.insertAdjacentElement("beforeend", boxWindow);
  boxOverlay.addEventListener("click", removeBox);
  document.body.classList.add("modal-open");
  const loader = document.createElement("div");
  loader.id = "WUB_load";
  loader.style.display = "block";
  const loaded = () => {
    boxWindow.style.visibility = "visible";
    document.body.dispatchEvent(new Event("wubox:load"));
    loader.remove();
    refreshBox();
  };
  document.body.insertAdjacentElement("beforeend", loader);
  const baseURL = url.split("?")[0];
  const urlString = /\.jpg$|\.jpeg$|\.png$|\.gif$|\.bmp$/;
  const isImage = !!baseURL.toLowerCase().match(urlString);
  if (isImage) {
    createImageBox(boxWindow, loaded, url, caption, imageGroup);
  } else {
    const queryString = url.replace(/^[^\?]+\??/, "");
    const searchParams = new URLSearchParams(queryString);
    const params = {
      width: parseInt(searchParams.get("width") || "") || 630,
      height: parseInt(searchParams.get("height") || "") || 440,
      modal: !!searchParams.get("modal") || false,
      inlineId: searchParams.get("inlineId") || ""
    };
    if (url.includes("WUB_iframe")) {
      createIframeBox(boxWindow, boxOverlay, loaded, url, caption, params);
    } else if (url.includes("WUB_inline")) {
      createInlineBox(boxWindow, boxOverlay, loaded, caption, params);
    } else {
      createAjaxBox(boxWindow, boxOverlay, loaded, url, caption, params);
    }
    (_a = document.getElementById("WUB_closeWindowButton")) == null ? void 0 : _a.addEventListener("click", removeBox);
  }
  const closeBoxWindowButton = document.getElementById("WUB_closeWindowButton");
  const closeIcon = closeBoxWindowButton == null ? void 0 : closeBoxWindowButton.querySelector(".wutb-close-icon");
  if (closeIcon && !!(closeIcon.offsetWidth || closeIcon.offsetHeight || closeIcon.getClientRects().length)) {
    closeBoxWindowButton.focus();
  }
}
function initForm(form) {
  form.addEventListener("submit", formSubmit(form));
}
const setBodyListeners = (domChunk) => {
  document.body.addEventListener("wubox:iframe:loaded", () => {
    var _a;
    (_a = document.getElementById("WUB_window")) == null ? void 0 : _a.classList.remove("wubox-loading");
  });
  document.body.addEventListener("wubox:load", () => {
    const form = document.querySelector("#WUB_ajaxContent .wu_form");
    if (!form) {
      return;
    }
    initForm(form);
    wu_initialize_editors();
  });
};
const onClickEvent = (event) => {
  event.preventDefault();
  const target = event.currentTarget;
  const caption = target.title || target.name || "", url = target.href || target.alt, imageGroup = target.rel || false;
  showBox(caption, url, imageGroup);
  target.blur();
};
const initBox = (domChunk, addGlobalListeners = false, addMutationObserver = false) => {
  document.querySelectorAll(domChunk).forEach((el) => {
    el.removeEventListener("click", onClickEvent);
    el.addEventListener("click", onClickEvent);
  });
  if (addGlobalListeners) {
    setBodyListeners();
  }
  if (addMutationObserver) {
    const observerOptions = {
      childList: true,
      subtree: true
    };
    const observer = new MutationObserver(() => {
      initBox(domChunk, false, false);
    });
    observer.observe(document.body, observerOptions);
  }
};
const removeBox = () => {
  var _a, _b, _c;
  (_a = document.getElementById("WUB_ImageOff")) == null ? void 0 : _a.removeEventListener("click", removeBox);
  (_b = document.getElementById("WUB_closeWindowButton")) == null ? void 0 : _b.removeEventListener("click", removeBox);
  document.body.classList.remove("modal-open");
  (_c = document.getElementById("WUB_load")) == null ? void 0 : _c.remove();
  fadeOutEffect(document.getElementById("WUB_window"), 200);
  fadeOutEffect(document.getElementById("WUB_overlay"), 150, () => {
    document.body.dispatchEvent(new Event("wubox:unload"));
    document.querySelectorAll("#WUB_window, #WUB_overlay, #WUB_HideSelect").forEach((el) => el.remove());
    document.body.dispatchEvent(new Event("wubox:removed"));
  });
};
const refreshBox = () => {
  const form = document.querySelector("#WUB_ajaxContent .wu_form");
  if (!form) {
    return;
  }
  wu_initialize_editors();
  const content = document.getElementById("WUB_ajaxContent");
  const boxWindow = document.getElementById("WUB_window");
  content.style.height = "100vh";
  const max_height = window.innerHeight - 120;
  const height = form.offsetHeight >= max_height ? max_height : form.offsetHeight + 1;
  boxWindow.style.transition = "margin 200ms";
  content.style.height = height + "px";
  boxWindow.style.marginTop = "-" + height / 2 + "px";
};
const setBoxWidth = (width) => {
  const content = document.getElementById("WUB_ajaxContent");
  const boxWindow = document.getElementById("WUB_window");
  if (!content) {
    return;
  }
  content.style.transition = "width 150ms";
  boxWindow.style.transition = "margin 150ms";
  content.style.width = width + "px";
  boxWindow.style.marginLeft = "-" + width / 2 + "px";
  boxWindow.style.width = width + "px";
  setTimeout(() => {
    refreshBox();
  }, 150);
};
window.wubox = {
  /**
   * Initializes the box.
   *
   * @param domChunk The DOM chunk to be used as the box content.
   * @param addGlobalListeners Whether or not to add global listeners.
   */
  init: initBox,
  /**
   * Progarmmatically shows the box.
   *
   * @param caption The title of the box.
   * @param url The URL to be loaded in the box.
   * @param imageGroup The image group to be used in the box.
   */
  show: showBox,
  /**
   * Removes the current opened box.
   *
   */
  remove: removeBox,
  /**
   * Refreshes the current opened box.
   */
  refresh: refreshBox,
  /**
   * set the box width.
   * @param width
   */
  width: setBoxWidth
};
window.addEventListener("DOMContentLoaded", () => {
  window.wubox.init(".wubox", true, true);

  // Mark wubox as ready and process any queued early clicks
  window.__wuboxReady = true;

  // Remove the early click listener - no longer needed
  if (window.__wuboxEarlyClickHandler) {
    document.removeEventListener('click', window.__wuboxEarlyClickHandler, true);
    delete window.__wuboxEarlyClickHandler;
  }

  if (window.__wuboxEarlyClicks && window.__wuboxEarlyClicks.length > 0) {
    // Remove duplicates - only keep unique elements
    const uniqueClicks = [...new Set(window.__wuboxEarlyClicks)];

    uniqueClicks.forEach((target) => {
      const caption = target.title || target.name || '';
      const url = target.href || target.alt;
      const imageGroup = target.rel || false;
	  target.style.cursor = '';
      window.wubox.show(caption, url, imageGroup);
    });
    window.__wuboxEarlyClicks = [];
  }
});
})()
