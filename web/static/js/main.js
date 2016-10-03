$(function() {
    $('.fancybox').each(function(){
        $(this).fancybox({
            href: $(this).prop('href').replace('#', '-')
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
});
