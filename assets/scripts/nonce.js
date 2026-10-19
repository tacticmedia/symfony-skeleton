// See: https://github.com/hotwired/turbo/issues/294#issuecomment-877842232
// See: https://github.com/nelmio/NelmioSecurityBundle/issues/321
document.addEventListener('turbo:before-fetch-request', (event) => {
    event.detail.fetchOptions.headers['X-CSP-Nonce'] = document.querySelector('meta[name="csp-nonce"]').content;
});
