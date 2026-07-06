let page = 1;
let loading = false;
let endReached = false;

window.onscroll = function(){

if(loading || endReached) return;

if((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 300){

loadMore();

}

};

function loadMore(){

loading = true;

fetch("/backend/posts/load_more.php?page=" + page)

.then(r => r.text())

.then(html => {

html = html.trim();

if(html === ""){
endReached = true;
return;
}

document.getElementById("feed").insertAdjacentHTML("beforeend", html);

page++;

})

.finally(() => {
loading = false;
});

}

$(document).on('click', '.btn-like, .btn-retweet', function(e) {
    var $btn = $(this);
    var href = $btn.attr('href');
    if (!href) return;
    e.preventDefault();
    $.getJSON(href, function(data) {
        if (!data.success) return;
        var $count = $btn.find('.count');
        if ($btn.is('.btn-like')) {
            $btn.toggleClass('liked', data.liked);
        } else {
            $btn.toggleClass('retweeted', data.retweeted);
        }
        if ($count.length) $count.text(data.count);
    }).fail(function() { window.location.href = href; });
});

function pollNotifications() {
    $.getJSON('/backend/misc/notification_count.php', function(data) {
        $('.notification-badge').each(function() {
            var count = data.count;
            if (count > 0) {
                $(this).text(count).show();
            } else {
                $(this).hide();
            }
        });
    });
}
setInterval(pollNotifications, 30000);