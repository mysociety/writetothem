/* Can be removed when Safari supports CSS counters... */
if (navigator.vendor.indexOf('Apple') != -1) {
    window.onload = function(){
        var bc = document.getElementById('breadcrumbs');
        if (bc) {
            li = bc.getElementsByTagName('li');
            for (var i=0; i<li.length; i++) {
                li[i].innerHTML = (i+1) + '. ' + li[i].innerHTML;
            }
        }
    }
}
