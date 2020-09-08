# geekstuff-it/php-fpm-nginx-alpine-dockerizer

This console php apps is meant to quickly initialize a php project as simple and as complete as possible
to both do development and build fully optimized and self-contained separate docker images for php-fpm and nginx.

It's meant to be used with image geekstuffreal/php-fpm-alpine and the
`php-init` script in it that will download this project and use it.

To fully see how it's used, head over to https://github.com/geekstuff-it/docker-php-fpm-alpine

## TODO
- fix issue where new project got latest tag for php-fpm
- add env var in base nginx box to let us control the timeouts differently in dev and prod.  
  (in dev, we will put a long timeout to avoid nginx getting in the way with xdebug)
