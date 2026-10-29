#!/bin/bash

docker run -d \
  --name "${3}-phpmyadmin" \
  -p 8080:80 \
  -e PMA_HOSTS="${2}-mysql-1,${2}-tests-mysql-1" \
  -e PMA_VERBOSES="Development,Test" \
  -e PMA_USER=root \
  -e PMA_PASSWORD=password \
  --network "$1" \
  phpmyadmin
