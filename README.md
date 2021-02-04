# WriteToThem

[WriteToThem](https://www.writetothem.com/) lets you contact elected
representatives in the United Kingdom.

If you want to build your own site for writing to people, we recommend you take
a look at [WriteIt](https://github.com/ciudadanointeligente/write-it) instead.
WriteToThem is a legacy project with many particular quirks, whereas WriteIt was
built from the start to be more flexible and easier to use.

## Developing WriteToThem

This repository contains a simple development environment based on Docker Compose.

Currently, this environment can be used for making changes to the front-end of the
site - HTML, styles, etc. Additional integration for full back-end testing (such as
representative lookup, etc) is not currently available.

Assuming you have Docker installed locally, you should be able to start the 
development environment by running:

    docker-compose up

This will start two containers (one for the app and a Postgres database) in the
foreground. You can stop the environment by pressing `CTRL-C`.

If you'd prefer them to run in the background, add the `-d` flag:

    docker-compose up -d

To stop the environment in this case run:

    docker-compose down

The first time you run the environment, a local image will be built for the app
container and the database will have the schema loaded automatically.

Once the environment is running, it should be accessible at http://127.0.0.1.xip.io:8085/
(note that you'll get an error if you simply visit localhost:8085 at present).

If you need to rebuild the app container, you can do so by running:

    docker-compose build

Changes made to styles, etc, (as described in the next section) should be
reflected when a page is reloaded as your local working copy is mapped to
the document root of the app container.

### Stylesheets

WriteToThem uses the [Foundation framework](http://foundation.zurb.com/),
and styles are compiled using [Compass](http://compass-style.org/).

Most people prefer to manage their Ruby Gems using Bundler. If you don’t
already have it, you can install it like so:

    gem install bundler

Then you can tell Bundler install the Gems for this project:

    bundle install

And finally, change into the Sass directory, and compile the Sass into CSS:

    cd web/static
    bundle exec compass compile

If you are making frequent changes, you can tell Compass to watch the
directory for updates, and recompile the CSS files as necessary:

    cd web/static
    bundle exec compass watch

## Acknowledgements

Thanks to [Browserstack](https://www.browserstack.com/) who let us use their
web-based cross-browser testing tools for this project.
