#! /bin/bash

docker build -t local-kochbuch:latest .
docker container rm kochbuch-local
docker container run --name kochbuch-local \
    --mount type=bind,source=/home/stefan/Dev/Kochbuch-Container/config,target=/config \
    --mount type=bind,source=/home/stefan/Dev/Kochbuch-Container/media,target=/media \
    --mount type=bind,source=/home/stefan/Dev/Kochbuch/src,target=/var/www/html \
    -p 8080:80 \
    local-kochbuch:latest
