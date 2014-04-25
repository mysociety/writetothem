# WriteToThem

[WriteToThem](https://www.writetothem.com/) lets you contact elected representitives in the United Kingdom.

If you want to build your own site for writing to people, we recommend you take
a look at [WriteIt](https://github.com/ciudadanointeligente/write-it) instead.
WriteToThem is a legacy project with many particular quirks, whereas WriteIt was
built from the start to be more flexible and easier to use.

## Developing WriteToThem

### Stylesheets

WriteToThem uses the [Foundation framework](http://foundation.zurb.com/), and
styles are compiled using [Compass](http://compass-style.org/). Before you start
editing files you will need some prerequisites, which can be installed as
follows (you may need to use `sudo`):

* `gem install zurb-foundation` will install the necessary components of the framework.
* `gem install compass` will install Compass, ready to compile assets.

The Sass files used to compile styles are located in `web/static/sass`. To
compile them, `cd` to the `web/static` directory and run `compass compile`. If
you are making frequent changes, `compass watch` will watch the directory for
changes and recompile when necessary.
