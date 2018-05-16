# WriteToThem

[WriteToThem](https://www.writetothem.com/) lets you contact elected
representatives in the United Kingdom.

If you want to build your own site for writing to people, we recommend you take
a look at [WriteIt](https://github.com/ciudadanointeligente/write-it) instead.
WriteToThem is a legacy project with many particular quirks, whereas WriteIt was
built from the start to be more flexible and easier to use.

## Developing WriteToThem

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
