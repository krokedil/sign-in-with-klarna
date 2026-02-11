const $ = jQuery;
const { klarna_interoperability } = await import("@klarna/interoperability_token");
let configData = {};
const params = document.getElementById(
    'wp-script-module-data-@klarna/siwk'
);

if (params?.textContent) {
    try { configData = JSON.parse(params.textContent); } catch { }
}

const siwk = {
    Klarna: null,
    params: null,
    button: null,
    buttonWrapper: "#klarna-identity-button",

    mount: function () {
        siwk.button = siwk.Klarna.Identity.button({
            scope: siwk.params.scope,
            redirectUri: siwk.params.redirect_uri,
            locale: siwk.params.locale,
            theme: siwk.params.theme,
            shape: siwk.params.shape,
            alignment: siwk.params.alignment,
            initiationMode: 'REDIRECT',
            interactionMode: 'REDIRECT',
        });

        // Only mount and register the events if the buttonWrapper exists.
        const $buttonWrapper = $(siwk.buttonWrapper);
        if ($buttonWrapper === undefined || $buttonWrapper.length === 0) {
            return;
        }

        siwk.Klarna.Identity.on("signin", siwk.onSignin);
        siwk.Klarna.Identity.on("error", siwk.onError);
        siwk.button.mount(siwk.buttonWrapper);
    },

    onSignin: async function (response) {
        const { user_account_linking } = response
        const { user_account_linking_id_token: id_token, user_account_linking_refresh_token: refresh_token } =
            user_account_linking

        jQuery.ajax({
            type: "POST",
            url: siwk.params.sign_in_from_popup_url,
            data: {
                url: window.location.href,
                id_token,
                refresh_token,
                nonce: siwk.params.sign_in_from_popup_nonce,
            },
            success: (data) => {
                if (data.success) {
                    const { redirect } = data.data
                    window.location = redirect
                } else {
                    console.warn("siwk sign-in failed", data)
                }
            },
            error: (error) => {
                console.warn("siwk sign-in error", error)
            },
        })
    },

    onError: async function (error) {
        console.warn("siwk error", error)
    },

    init: async function (e) {
        siwk.params = configData;
        siwk.Klarna = klarna_interoperability.Klarna;

        siwk.mount();
    }
}

$('body').on('klarna_wc_sdk_loaded', siwk.init);
