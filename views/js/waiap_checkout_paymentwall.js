(function ($) {
    initialize();

    function initialize() {
        //check if we hace to redirect to payment step
        var autoClickWaiap = false;

        //var searchParams = new URLSearchParams(window.location.search);
        parseUrlParams('request_id');
        if (parseUrlParams('request_id') && parseUrlParams('method')) {
            if (parseFloat(ps_version) >= 1.7){
                window.location.hash = PS_17_PAYMENT_STEP_HASH;
                autoClickWaiap = true;
            }else{
                if (osc_checkout === "0" && !parseUrlParams('step')){
                    window.location.hash = PS_16_OSC_PAYMENT_STEP_HASH;
                    window.location.search = window.location.search + "&" + PS_16_PAYMENT_STEP;
                }else{
                    window.location.hash = PS_16_OSC_PAYMENT_STEP_HASH;
                }
            }
        }
        addListeners();
        $(document).ready(function(){
            if (autoClickWaiap) {
                //trigger click on payment method and accept terms (should be accepted if payment wall is trying to validate payment)
                var regex = new RegExp(/payment-option-[0-9]*/);
                var parent_element = $('.payment-wall').parent();
                parent_element.show();
                var payment_option = parent_element[0].id;
                var match_option = payment_option.match(regex);
                if (match_option) {
                    $('label[for=' + match_option + ']').trigger('click');
                }
                $('.js-terms').trigger('click');
            }
        }.bind(this));
    }

    function addListeners(){
        $(document).on('waiap_draw_payment_wall', function(){
            $('#waiap-terms-alert').hide();
            renderPaymentWall();
        });
        if ($('#uniform-cgv span').hasClass("checked")) {
            $('#waiap-terms-alert').hide();
            renderPaymentWall();
        }
        $(document).on('change','.payment-options', function(){
            if ($('#payment-confirmation').length) {
                if ($('#app').is(':visible')) {
                    $('#payment-confirmation').hide();
                    if ($('#conditions-to-approve input').is(':checked')){
                        $('#waiap-terms-alert').hide();
                        renderPaymentWall();
                    }else {
                        $("#app").empty();
                        $("#app").removeAttr("class");
                        $('#waiap-terms-alert').show();
                    }
                }else{
                    $('#payment-confirmation').show();
                }
            }
        });
        $(document).on('change', '#conditions-to-approve', function(){
            if ($('#payment-confirmation').length) {
                if ($('#app').is(':visible')) {
                    $('#payment-confirmation').hide();
                    if ($('#conditions-to-approve input').is(':checked')) {
                        $('#waiap-terms-alert').hide();
                        renderPaymentWall();
                    }else{
                        $("#app").empty();
                        $("#app").removeAttr("class");
                        $('#waiap-terms-alert').show();
                    }
                } else {
                    $('#payment-confirmation').show();
                }
            }
        });     
    }

    function updateRedirectListeners() {
        window.PaymentWall.start();
        document.addEventListener('payment_wall_load', function () {
            window.PaymentWall.listenTo(document.getElementById('app'), "payment_wall_loaded", function () {
                var placeholder = document.getElementById('app');
                //NEW SERVER TO SERVER
                if (!this.processedRedirect) {
                    window.PaymentWall.listenTo(placeholder, "payment_wall_drawn", function (data) {
                        if (parseUrlParams('request_id') && parseUrlParams('method')) {
                            var request_id  = parseUrlParams('request_id');
                            var method      = parseUrlParams('method');
                            var error       = parseUrlParams('error');
                            document.getElementById('app').dispatchEvent(window.pwall.dispatch('process_redirect', { "error": error, "method": method, "request_id": request_id }));
                            this.processedRedirect = true;
                        }
                    }.bind(this));
                }
                //PAYMENT OK
                window.PaymentWall.listenTo(placeholder, "payment_wall_payment_ok", function (data) {
                    console.log("PAYMENT OK,  REDIRECTING TO ONEPAGE SUCCESS");
                    var url_encoded = getCookie("success_redirect");
                    deleteCookie("success_redirect");
                    window.location.replace(decodeURIComponent(url_encoded));
                });
                if (getCookie("waiap_payment_ko")) {
                    deleteCookie("waiap_payment_ko", "/");
                    $("#app").prepend('<p id="waiap-terms-alert" class="alert alert-danger" role="alert" data-alert="danger">' + waiap_payment_error + '</p>');
                }   
            }.bind(this));
        }.bind(this));
    }

    function updatePaymentWallDataset(newScript) {
        $.ajax({
            url: waiap_quote_rest,
            async: false,
            cache: false
        }).done(function (amount) {
            newScript.dataset.placeholder = "#app";
            newScript.dataset.groupId = waiap_customerId;
            newScript.dataset.amount = amount;
            newScript.dataset.currency = waiap_currency;
            newScript.dataset.endpoint = waiap_backend_url;
        }).fail(function (data) {
            newScript.dataset.placeholder = "#app";
            newScript.dataset.groupId = null;
            newScript.dataset.amount = null;
            newScript.dataset.currency = null;
            newScript.dataset.url = null;
        });

    }

    function renderPaymentWall() {
        log("RENDERING PAYMENT WALL");
        if ($('#pwallappjs').length) {
            updatePaymentWallDataset($('#pwallappjs').get(0));
            updateRedirectListeners();
        } else {
            var head = document.getElementsByTagName('head')[0];
            var newScript = document.createElement('script');
            newScript.id = "pwallappjs";
            newScript.src = waiap_app_js;
            newScript.type = 'text/javascript';
            newScript.onload = updateRedirectListeners;
            updatePaymentWallDataset(newScript);
            head.parentNode.appendChild(newScript);
        }
    }

    /**
     * Get the value of a cookie
     * Source: https://gist.github.com/wpsmith/6cf23551dd140fb72ae7
     * @param  {String} name  The name of the cookie
     * @return {String}       The cookie value
     */
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
    };

    function deleteCookie(name, path, domain) {
        if (getCookie(name)) {
            document.cookie = name + "=" +
                ((path) ? ";path=" + path : "") +
                ((domain) ? ";domain=" + domain : "") +
                ";expires=Thu, 01 Jan 1970 00:00:01 GMT";
        }
    }

    function log() {
        var args = Array.prototype.slice.call(arguments, 0);
        args.unshift("[SIPAY DEBUG]");
        console.log.apply(console, args);
    }

    function parseUrlParams(name){
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results == null){
            return null;
        }
        else {
            return decodeURI(results[1]) || 0;
        }
    }
})(jQuery);
