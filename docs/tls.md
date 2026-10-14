# TLS Certificates

Every site takes its certificate from Caddy's local certificate authority through `tls internal`:
the app at `https://localhost` in `php` and in `php-e2e`, set in `frankenphp/Caddyfile`, and the
Mailpit UI at `https://localhost:8025`, set in `frankenphp/sites-dev/mailpit.caddyfile`.

## Trusting the Authority

Caddy generates the authority on first boot and persists it to `frankenphp/pki/authorities/local/`,
which is gitignored. The authority is yours alone: never commit it, and never share the private key.
Anyone holding it can issue a trusted certificate for any host your machine talks to.

Your machine does not trust that authority until you add it to the host trust store. Run the command
for your platform once per developer machine, and again after deleting the directory to rotate the
authority.

<!-- markdownlint-disable MD013 -->

### Linux

```console
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt /usr/local/share/ca-certificates/root.crt && sudo update-ca-certificates
```

### Mac

```console
docker cp $(docker compose ps -q php):/data/caddy/pki/authorities/local/root.crt /tmp/root.crt && sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain /tmp/root.crt
```

### Windows

```console
docker compose cp php:/data/caddy/pki/authorities/local/root.crt %TEMP%/root.crt && certutil -addstore -f "ROOT" %TEMP%/root.crt
```

<!-- markdownlint-enable MD013 -->

The containers trust the authority by themselves. `CADDY_GLOBAL_OPTIONS` carries
`skip_install_trust` in every image except `frankenphp_dev`, so in the dev image Caddy adds the root
certificate to the container's system bundle on start. Caddy stops there, and Chromium reads the NSS
database instead, so the entrypoint runs `frankenphp/trust-local-ca.sh`, which adds the root to
`/root/.pki/nssdb`. Without it the journey browser reports `NET::ERR_CERT_AUTHORITY_INVALID` on every
HTTPS page.

Caddy creates the authority as it starts, which is after the entrypoint runs, so on a fresh checkout
the `php` container trusts it from its second start. The `php-e2e` container, where the journeys
drive `https://localhost`, mounts the same authority directory and only starts once `php` is
healthy, so it trusts the certificate from its first boot, in CI too.

## Recovering the Authority

`docker compose restart php` failing with "not a directory" on
`frankenphp/pki/authorities/local/root.crt` means the persisted files became empty directories on
the host, so the bind mount cannot be re-established. Remove them and let Caddy regenerate:

```console
rmdir frankenphp/pki/authorities/local/root.crt frankenphp/pki/authorities/local/root.key
docker compose up -d php
```

The authority is new, so trust it again with the command for your platform above.

## Using Custom TLS Certificates

To use self-signed certificates created with [mkcert](https://github.com/FiloSottile/mkcert):

1. Locally install `mkcert`
2. Create the folder storing the certs:

   ```console
   mkdir -p frankenphp/certs
   ```

3. Generate the certificates for your local host, such as `server-name.localhost`:

   <!-- markdownlint-disable MD013 -->

   ```console
   mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key "server-name.localhost"
   ```

   <!-- markdownlint-enable MD013 -->

4. Add the `CADDY_EXTRA_CONFIG` environment variable and the volume to the `php` service in
   `compose.override.yaml`:

   ```diff
    php:
      environment:
   +    CADDY_EXTRA_CONFIG: |
   +      https:// {
   +          tls /etc/caddy/certs/tls.pem /etc/caddy/certs/tls.key
   +      }
        # ...
      volumes:
   +    - ./frankenphp/certs:/etc/caddy/certs:ro
        # ...
   ```

5. Recreate the `php` service with `SERVER_NAME` set to that host, so the app's site block answers
   it:

   ```console
   SERVER_NAME=server-name.localhost docker compose up -d php
   ```

Put the directive in `CADDY_EXTRA_CONFIG`, not in `CADDY_SERVER_EXTRA_DIRECTIVES`. The app's site
block also listens on `php:80`, which is plain HTTP, and Caddy refuses a `tls` directive there.
