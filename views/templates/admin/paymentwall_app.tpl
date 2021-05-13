<script src={$waiap_pwall_bundle}></script>
<link href={$waiap_pwall_css} rel=stylesheet>
<script src="https://cdn.jsdelivr.net/gh/waiap/javascript-sdk@2.0.2/build/pwall-sdk.min.js"></script>
<script>
    (function ($) {
        $("input[id*='custom_border_color']").on("change paste keyup", function() {
            var value = $(this).val();
            if (!{literal}/^#([0-9A-F]{3}){1,2}$/i{/literal}.test(value)) {
                $(this).addClass("alert-danger");
                if (!$(this).parent().find(".custom-color-error").length) {
                $(this).parent().append('<label generated="true" class="alert-danger custom-color-error">'+"{$waiap_invalid_color}"+'</label>');
                }
            } else {
                $(this).removeClass("alert-danger");
                if ($(this).parent().find(".custom-color-error").length) {
                $(this).parent().find(".custom-color-error").remove();
                }
            }
        });
        const client = new PWall('{$waiap_enviroment}', false);
        $("#fieldset_0 > .form-wrapper").append('<div id="waiap-app"><div>');
        initAccordion();
        function initAccordion(){
            var allPanels = $('.form-wrapper').hide();
            var allSavePanels = $('.panel-footer').hide();
            initStylesAndElements();
            $('.panel-heading').on('click', function (event) {
                event.preventDefault();
                allPanels.hide();
                allSavePanels.hide();
                resetAllArrows();
                toggleArrow($(this).children('i.ez-arrow'));
                $(this).parent().find('.form-wrapper').show();
                $(this).parent().find('.panel-footer').show();
                initCheckoutAdminSection($(this).parent(), null);
            });
            {* $('#fieldset_2_2 > .form-wrapper > .form-group').not(":eq(0)").on('click', function (event) {
                event.preventDefault();
                resetAllArrows($(this));
                toggleArrow($(this).children('i.ez-arrow'));
                initCheckoutAdminSection($(this).closest(".panel"),$(this));
            }); *}
        }
        function initCheckoutAdminSection(parent, element){
            $("#app").remove();
            if(parent.attr('id') == 'fieldset_0'){
                var backoffice = client.backoffice();
                backoffice.backendUrl('{$waiap_pwall_controller}');
                backoffice.appendTo("#waiap-app");
                backoffice.setTags("fisico");
                backoffice.init();
            }else{
                $("#waiap-ec-admin").remove();
                var id = $(parent).attr('id');
                $(parent).find('.form-group:nth-child(8)').after("<div id='waiap-ec-admin'>");
                $('#waiap-ec-admin').css('margin-bottom','2rem');
                var backoffice = client.backoffice();
                backoffice.backendUrl('{$waiap_pwall_controller}');
                backoffice.appendTo('#waiap-ec-admin');
                backoffice.setProfile(getSectionProfile(id));
                backoffice.setTags("express");
                backoffice.setIsExpressCheckout(true);
                backoffice.init();
            }
        }
        function getSectionProfile(id){
            if(id === "fieldset_2_2"){
                return "woocommerce_product_page";
            }else if(id === "fieldset_3_3"){
                return "woocommerce_minicart";
            }else{
                return "woocommerce_cart";
            }
        }
        function initStylesAndElements(){
            $('.panel-heading').append('<i class="ez-arrow down">');
            $('#fieldset_1_1 > .form-wrapper > .form-group').not(":eq(0)").css('position','relative')
            $('#fieldset_1_1 > .form-wrapper > .form-group').not(":eq(0)").append('<i class="ez-arrow down">');
        }
        
        function toggleArrow(element){
            element.addClass('up').removeClass('down');
        }
        function resetAllArrows(element){
            if(element){
                element.parent().find('i.ez-arrow').addClass('down').removeClass('up');
            }else{
                $('i.ez-arrow').addClass('down').removeClass('up');
            }
        }
    })(jQuery);
</script>