// Watch pages (portal/watch-recording.php, watch-pre-recording.php): hide the spinner once the Bunny player has loaded.
(() => {
    const iframe = document.getElementById('videoIframe');
    const loader = document.getElementById('videoLoader');
    if (!iframe || !loader) return;
    const done = () => {
        loader.style.opacity = '0';
        setTimeout(() => { loader.style.display = 'none'; }, 400);
        iframe.style.display = 'block';
    };
    iframe.addEventListener('load', done);
    setTimeout(done, 8000); // never leave a spinner over a player that is slow to report
})();
