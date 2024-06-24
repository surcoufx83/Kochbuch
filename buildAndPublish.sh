#! /bin/bash

rm -r ./src/vendor/
rm ./src/composer.lock 
docker build -t surcouf/kochbuch:latest .
docker push surcouf/kochbuch:latest
cd ./src/
composer install
cd ..
