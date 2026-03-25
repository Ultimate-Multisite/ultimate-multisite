#!/bin/bash

contaner_name_prefix=$(basename "$PWD")

if ! wp-env start; then
	echo "wp-env failed to start. Exiting."
	exit 1
fi

network=$(docker container ps --format "{{.Networks}}" | head -n 1)
network_id=${network%_default} # Remove '_default' from network

bash ./bin/phpmyadmin.sh "$network" "$network_id" "$contaner_name_prefix"
bash ./bin/mailpit.sh "$network" "$contaner_name_prefix"
