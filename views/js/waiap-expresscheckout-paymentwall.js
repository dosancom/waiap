(function ($) {
  $(document).ready(function () {
    if(waiap_ec_config){
     
      waiapExpressCheckoutState.setCurrentProfile(waiap_ec_config, waiap_ec_config.element);

      // $(document.body).on('updated_cart_totals', function () {
      //   waiapExpressCheckoutState.setCurrentProfile(waiap_ec_config, waiap_ec_config.element);
      // });
    }
    $(document).on('express_checkout_render', function(){
      waiapExpressCheckoutState.setCurrentProfile(waiap_ec_config, waiap_ec_config.element);
    })
    // if(waiap_ec_minicart_config){
    //   $('.cart-contents').mouseenter(function () {
    //     window.waiapExpressCheckoutState.setCurrentProfile(waiap_ec_minicart_config, waiap_ec_minicart_config.element);
    //   });
    //   $('.site-header-cart').mouseleave(function () {
    //     window.waiapExpressCheckoutState.revertProfile();
    //   });
    // }
  });
})(jQuery)