/**
 * @file
 * Youtube related functions for /peoples-choice page.
 */

Drupal.behaviors.piv_vote_youtube = {

  youtube_ready: false,
  players: [],

  /**
   * Create the players.
   *
   * We attempt to create as soon as possible, players will be created when
   * behaviors run and when youtube is ready. At the first load the behavior might
   * run before youtube is ready, it will re-run after youtube is ready.
   */
  createPlayers: function(context) {
    if (!this.youtube_ready) {
      return;
    }

    var behavior = this;
    jQuery('.contest-video > iframe', context).once('youtube').each(function() {
      let id = jQuery(this).attr('id');
      let player = new YT.Player(id, {
        events: {
          'onStateChange': behavior.onPlayerStateChange.bind(behavior)
        }
      });

      behavior.players.push(player);
    });
  },

  /**
   * Run when youtube is ready.
   */
  onYouTubeIframeAPIReady: function() {
    this.youtube_ready = true;
    this.createPlayers(document);
  },

  /**
   * Run on a video changes state.
   *
   * @see https://developers.google.com/youtube/iframe_api_reference#Events
   */
  onPlayerStateChange: function(event) {
    // When playing a video, pause the other videos.
    if (event.data == YT.PlayerState.PLAYING) {
      for (let i in this.players) {
        if (event.target.getVideoUrl() != this.players[i].getVideoUrl()) {
          this.players[i].pauseVideo();
        }
      }
    }
  },

  /**
   * Attach the behavior.
   */
  attach: function (context, settings) {
    // Make sure ajax loaded players will be created too.
    this.createPlayers(context);

    // Load the youtube library.
    jQuery('body').once('youtube').each(function() {
      var tag = document.createElement('script');
      tag.src = "https://www.youtube.com/iframe_api";
      var firstScriptTag = document.getElementsByTagName('script')[0];
      firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    });
  },

}

/**
 * Free function called by youtube api.
 */
function onYouTubeIframeAPIReady() {
  Drupal.behaviors.piv_vote_youtube.onYouTubeIframeAPIReady();
}
