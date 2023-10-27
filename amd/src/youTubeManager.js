class YouTubeManager {
    constructor() {
        this.player = null;
        this.playerState = null;
        this.currentTime = null;
        this.readyPromise = this.initYouTubeAPI();
        this.totalDuration = null;
    }

    initYouTubeAPI() {
        return new Promise((resolve, reject) => {
            // Initialize YouTube API
            window.onYouTubeIframeAPIReady = () => {
                // eslint-disable-next-line promise/catch-or-return
                this.modifyIframeIfPresent().then(() => {
                    this.createPlayer();
                    resolve();
                });
            };

            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            tag.onerror = () => reject(Error('Error loading YouTube API'));
            document.getElementsByTagName('head')[0].appendChild(tag);
        });
    }

    createPlayer() {
        // eslint-disable-next-line no-undef
        this.player = new YT.Player('another-love', {
            events: {
                'onReady': this.onPlayerReady.bind(this),
                'onStateChange': this.onPlayerStateChange.bind(this)
            }
        });
    }
    modifyIframeIfPresent() {
        return new Promise((resolve) => {
            const iframe = document.getElementsByTagName('iframe')[0];
            if (iframe) {
                // Modify the iframe code here
                // Set the id attribute for the iframe
                iframe.setAttribute('id', 'another-love');
                // Get the current src attribute
                let currentSrc = iframe.getAttribute('src');
                // Split the current src at the "?" character
                let srcParts = currentSrc.split('?');
                // Create the new src with "?enablejsapi=1" and the original video ID
                let newSrc = srcParts[0] + '?enablejsapi=1';
                // Update the src attribute with the modified URL
                iframe.setAttribute('src', newSrc);
            }
            resolve();
        });
    }
    // eslint-disable-next-line no-unused-vars
    onPlayerReady(event)
    {
        this.totalDuration = this.player.getDuration();
    }
    // eslint-disable-next-line no-unused-vars
    onPlayerStateChange(event) {
            // eslint-disable-next-line no-undef
        if (event.data === YT.PlayerState.PLAYING) {
            if (this.playerState !== 'playing') {
                this.playerState = 'playing';
                this.currentTime = this.player.getCurrentTime();
            }
        } else if (event.data === 2) {
            if (this.playerState !== 'paused') {
                this.playerState = 'paused';
                this.currentTime = this.player.getCurrentTime();
            }
        }
        return event.data;
    }
}
const youtubeManager = new YouTubeManager();
export default youtubeManager;
