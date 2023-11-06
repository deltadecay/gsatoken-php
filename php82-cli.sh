#!/bin/sh

docker run -it --rm --name php82-cli -v "$PWD":/usr/src/myapp -w /usr/src/myapp php:8.2.12-cli-alpine3.18 php $@

