#!/bin/bash

docker run -d \
	--name "${2}-mailpit" \
	-p 8025:8025 \
	-p 1025:1025 \
	--network "$1" \
	axllent/mailpit:latest
