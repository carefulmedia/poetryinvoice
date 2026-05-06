/** Restore old functionalities for jQuery. */

(function ($) {
  if (!$) return;

  if (typeof $.parseJSON !== 'function') {
    $.parseJSON = function (data) {
      if (data == null || data === '') return null;
      if (typeof data !== 'string') return data;
      return JSON.parse(data);
    };
  }
  if (typeof $.camelCase !== 'function') {
    var rmsPrefix = /^-ms-/;
    var rdashAlpha = /-([a-z])/g;
    function fcamelCase(_, letter) { return letter.toUpperCase(); }
    $.camelCase = function (string) {
      return string
        .replace(rmsPrefix, 'ms-')
        .replace(rdashAlpha, fcamelCase);
    };
  }
})(window.jQuery);
