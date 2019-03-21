(function ($) {
  $(document).ready(function () {
    if (!window.CKEDITOR) {
      return;
    }

    // XXX This will not wotk in IE
    var src = document.currentScript.src;
    H5PEditor.assets.js.push(src);

    // Register our plugin
    CKEDITOR.plugins.addExternal('enlighter', src.replace('extraplugins.js', 'plugins/enlighter/plugin.js'));
    H5PEditor.HtmlAddons = H5PEditor.HtmlAddons || {};
    H5PEditor.HtmlAddons.pre = H5PEditor.HtmlAddons.pre || {};
    H5PEditor.HtmlAddons.pre.pre = function (config, tags) {
      // Add the plugin.
      config.extraPlugins = (config.extraPlugins ? ',' : '') + 'enlighter';

      // Add plugin to toolbar.
      config.toolbar.push({
        name: "enlighter",
        items: ['Enlighter']
      });

      // Add our special tag
      tags.push('pre');
      tags.push('code');
    };
  });
})(H5P.jQuery);
