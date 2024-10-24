if ( siwk_params !== undefined ) {
    window.KlarnaSDKCallback = function ( klarna ) {
        klarna.Identity.on( "signin", async ( response ) => {
            const { user_account_linking } = response
            const { user_account_linking_id_token: id_token, user_account_linking_refresh_token: refresh_token } =
                user_account_linking

            jQuery.ajax( {
                type: "POST",
                url: siwk_params.sign_in_from_popup_url,
                data: {
                    url: window.location.href,
                    id_token,
                    refresh_token,
                    nonce: siwk_params.sign_in_from_popup_nonce,
                },
                success: ( data ) => {
                    if ( data.success ) {
                        const { redirect } = data.data
                        window.location = redirect
                    } else {
                        console.warn( "siwk sign-in failed", data )
                    }
                },
                error: ( error ) => {
                    console.warn( "siwk sign-in error", error )
                },
            } )
        } )

        klarna.Identity.on( "error", async ( error ) => {
            console.warn( "siwk error", error )
        } )
    }
}
