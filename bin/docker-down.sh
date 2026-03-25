#!/bin/bash

container_name_prefix=$(basename "$PWD")

docker container stop "${container_name_prefix}-phpmyadmin"
docker container rm "${container_name_prefix}-phpmyadmin"
docker container stop "${container_name_prefix}-mailpit"
docker container rm "${container_name_prefix}-mailpit"
wp-env stop
