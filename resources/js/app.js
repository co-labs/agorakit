import Echo from "laravel-echo";

// Bootstrap and Jquery
try {
  window.$ = window.jQuery = require('jquery/dist/jquery');
  require('bootstrap');

  /**
   * Add a scroll to top functionnalities
   *
   * @type {{default?}|{init: scrolltotop.init, controlHTML: string, controlattrs: {offsetx: number, offsety: number}, togglecontrol: scrolltotop.togglecontrol, state: {isvisible: boolean, shouldvisible: boolean}, keepfixed: scrolltotop.keepfixed, anchorkeyword: string, setting: {scrollduration: number, fadeduration: number[], startline: number, scrollto: number}, scrollup: scrolltotop.scrollup}}
   */
  let scrollToTop = require('./components/scrollToTop');
  scrollToTop.init();

} catch (e) {

}


// Open external links in blank tabs
require('./external_links');


// Trumbowyg wysiwyg editor
require('trumbowyg/dist/trumbowyg.min.js');
require('./components/trumbowyg.pasteembed');
// ...with mention plugin
require('trumbowyg/dist/plugins/mention/trumbowyg.mention.min.js');

// Unpoly
require('unpoly/dist/unpoly.min.js');
//require('unpoly/dist/unpoly-bootstrap3.min.js');

/**
 * Enable realtime server if realtimeMode is activated
 */
if (typeof window.__app.realtime !== 'undefined') {

  window.Pusher= require("pusher-js");

  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: window.__app.pusherKey,
    wsHost: window.__app.pusherHost,
    wsPort: window.__app.pusherPort,
    disableStats: true,
  });

  window.Echo.join('chat')
    .here(users => {
      this.users = users;
    })
    .joining(user => {
      this.users.push(user);
    })
    .leaving(user => {
      this.users = this.users.filter(u => u.id !== user.id);
    })
    .listenForWhisper('typing', ({id, name}) => {
      this.users.forEach((user, index) => {
        if (user.id === id) {
          user.typing = true;
          this.$set(this.users, index, user);
        }
      });
    })
    .listen('MessageSent', (event) => {
      this.messages.push({
        message: event.message.message,
        user: event.user
      });

      this.users.forEach((user, index) => {
        if (user.id === event.user.id) {
          user.typing = false;
          this.$set(this.users, index, user);
        }
      });
    });
}

// tribute
//import Tribute from "tributejs";
//window.Tribute = Tribute;


// Unpoly custom compilers (include after the rest)
//require('./compilers');
