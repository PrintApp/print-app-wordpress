# Send to Lulu: sandbox test shop

A throwaway WordPress + WooCommerce shop in Docker, running this repo's plugin, for
testing Send to Lulu against Lulu's **sandbox** (nothing is printed or charged). Not
shipped to WordPress.org: the release skips dot-folders.

Lulu downloads cover and interior PDFs from the shop, so the shop needs a public
address. `setup.sh` starts a temporary Cloudflare quick tunnel for that (no account;
a random `*.trycloudflare.com` address that changes on every start). The shop is
public while the tunnel runs: stop it when you're done (`docker compose stop tunnel`).

## Once

1. Create a sandbox account at https://developers.sandbox.lulu.com and generate an
   API key and secret on its API Keys page.
2. `cp .env.local.example .env.local` and fill in `LULU_CLIENT_KEY` and
   `LULU_CLIENT_SECRET`. `.env.local` is git-ignored.

## Run (Git Bash)

```
./setup.sh              # start; prints the shop address and the Lulu cover size
./setup.sh order        # a paid test order for the Lulu product, sent to Lulu
./setup.sh status       # the order's Lulu job, status and notes
./setup.sh refresh      # ask Lulu for the latest status
./setup.sh configure    # re-apply .env.local without restarting
./setup.sh down         # stop (add -v to delete the shop's data)
```

`order` needs `COVER_PROJECT_ID` in `.env.local`: a Print.App design or project sized
to the cover dimensions `setup.sh` prints. Lulu rejects covers of the wrong size, which
shows up in the order notes after `refresh`.

The shop admin is at `<shop address>/wp-admin`, user `admin`, with the password that
`setup.sh` saved in `.env.local`.

## Storefront (Customize button, cart, checkout)

The Print.App editor only opens on a domain's own store address or on `localhost`; on
the tunnel address it says "domain mismatch". So test the storefront at
`http://localhost:8088`, with the tunnel stopped and `home`/`siteurl` set back to it:

```
docker compose stop tunnel
docker compose run --rm -T cli wp option update home http://localhost:8088
docker compose run --rm -T cli wp option update siteurl http://localhost:8088
```

PHP notices and deprecations go to `wp-content/debug.log` in the shop
(`docker compose exec wordpress cat wp-content/debug.log`). WooCommerce keeps the shop
in "Coming soon" mode, so it's hidden from logged-out visitors on the public address.
