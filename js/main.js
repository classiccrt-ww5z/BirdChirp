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