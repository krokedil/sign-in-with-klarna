if (siwk_params !== undefined) {
  console.log('onSignIn defined.');
  function onSignIn(response) {
    const { access_token, refresh_token, id_token, expires_in } = response
    console.log('Sign in OK', response)
    jQuery.ajax({
      type: 'POST',
      url: siwk_params.sign_in_url,
      data: {
        nonce: siwk_params.sign_in_nonce,
        access_token: access_token,
        refresh_token: refresh_token,
        id_token: id_token,
        expires_in: expires_in,

      },
      success: (data) => {
        console.log(data)
        location.reload();
      },
      errror: (data) => {
        console.log(data)
      }
    });
  }
  console.log('onSignInError defined.');
  function onSignInError(e) {
    console.log('Sign in error', e)
  }
}