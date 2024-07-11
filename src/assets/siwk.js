if (siwk_params !== undefined) {
  window.KlarnaSDKCallback = function (klarna) {
    klarna.Identity.on("signin", async (response) => {
      const { user_account_linking } = response;
      const { user_account_linking_id_token: id_token, user_account_linking_refresh_token: refresh_token  } = user_account_linking;

      jQuery.ajax({
        type: 'POST',
        url: siwk_params.sign_in_url,
        data: {
          id_token,
          refresh_token,
          action: 'siwk_login',
          nonce: siwk_params.sign_in_nonce
        },
        success: data => {
          if (data.success) {
            // Woo will sign-in the user, reload the page.
            location.reload();
          } else {
            console.warn('siwk sign-in failed', data);
          }
        },
        error: error => {
          console.warn('siwk sign-in error', error);
        }
      })


    });

    // 2. Listen for `error` event to handle error object
    klarna.Identity.on("error", async (error) => {
      console.warn('siwk error', error);
    });

    const siwkButton = klarna.Identity.button("klarna-identity-button");
    
    siwkButton.on('ready', async () => {
      // console.log('Button is ready')
    })

    siwkButton.on('clicked', async () => {
      // console.log('Button was clicked')
    })
  }
}