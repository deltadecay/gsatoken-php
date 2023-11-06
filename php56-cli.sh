#!/bin/sh

docker run -it --rm --name php56-cli -v "$PWD":/usr/src/myapp -w /usr/src/myapp php:5.6.40-cli-alpine php $@

