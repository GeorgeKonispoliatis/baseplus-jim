(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.mtflexsliderBreakingSlider = {
    attach: function (context, settings) {
      once('mtflexsliderBreakingSliderInit', ".view-mt-breaking-slider .flexslider", context).forEach(function(item) {
        $(item).flexslider({
          animation: drupalSettings.baseplus.flexsliderBreakingSliderInit.breakingEffect,        // Select your animation type, "fade" or "slide"
          slideshowSpeed: drupalSettings.baseplus.flexsliderBreakingSliderInit.breakingEffectTime,   // Set the speed of the slideshow cycling, in milliseconds
          prevText: "",
          nextText: "",
          pauseOnAction: true,
          useCSS: false,
          controlNav: false,
          pausePlay: true,
          directionNav: true
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
