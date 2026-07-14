$(function() {
    $('.fancybox').each(function(){
        //   about-qa#multireps -> about-qa?fragment=multireps   (one section)
        //   about-personal     -> about-personal?fragment=wholepage
        var href = $(this).prop('href');
        if (href.indexOf('#') !== -1) {
            href = href.replace('#', (href.indexOf('?') === -1 ? '?' : '&') + 'fragment=');
        } else {
            href += (href.indexOf('?') === -1 ? '?' : '&') + 'fragment=wholepage';
        }
        $(this).fancybox({
            href: href
        });
    });

    $('.facebook-share-button').on('click', function(e){
        e.preventDefault();
        FB.ui({
            method: 'share',
            href: $(this).attr('data-url'),
            quote: $(this).attr('data-text')
        }, function(response){});
    });

    $('.js-fixed-thead').fixedThead();

    var toggleContactOption = function( $option, show ) {
        var $siblings = $option.siblings('.contact-option');
        var $btn = $option.find('h3 button');
        var $content = $option.find('.contact-option__detail');

        if ( show ) {
            $btn.attr('aria-expanded', 'true');
            $content.removeAttr('hidden');
            $option.addClass('contact-option--active');
            $siblings.each(function(){
                toggleContactOption( $(this), false );
            });
        } else {
            $btn.attr('aria-expanded', 'false');
            $content.attr('hidden', 'hidden');
            $option.removeClass('contact-option--active');
        }
    };

    $('.js-contact-options-accordion .contact-option').each(function(){
        var $option = $(this);
        var $btn = $(this).find('h3 button');

        toggleContactOption( $option, false );

        $btn.on('click', function(){
            toggleContactOption( $option, $btn.attr('aria-expanded') === 'false' );
        });
    });
});
