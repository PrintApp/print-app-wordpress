#!/usr/bin/env bash
# Starts the Lulu sandbox shop and points it at this run's tunnel address.
#   ./setup.sh            start (first run installs WordPress + WooCommerce) and configure
#   ./setup.sh configure  re-apply .env.local (keys, product) without restarting
#   ./setup.sh order      place a paid test order and send it to Lulu
#   ./setup.sh status     the last test order's Lulu status and notes
#   ./setup.sh refresh    ask Lulu for the last order's latest status
#   ./setup.sh down       stop (keeps the data); add -v to wipe it
set -euo pipefail
cd "$(dirname "$0")"
export MSYS_NO_PATHCONV=1

touch .env.local
load_env() {	# .env.local, without overriding what the command line already set
	while IFS='=' read -r key value || [ -n "$key" ]; do
		key=${key//[[:space:]]/}
		[[ -z "$key" || "$key" == \#* ]] && continue
		value=${value%$'\r'}
		[ -z "${!key:-}" ] && export "$key=$value"
	done < .env.local
	return 0
}
load_env

wp() {
	docker compose run --rm -T \
		-e WP_CLI_CACHE_DIR=/tmp/wp-cli-cache -e LULU_CLIENT_KEY -e LULU_CLIENT_SECRET -e LULU_POD_PACKAGE_ID -e LULU_PAGE_COUNT -e COVER_PROJECT_ID \
		cli wp "$@"
}

case "${1:-up}" in
	down)   docker compose down "${@:2}"; exit ;;
	status) wp eval-file /sandbox/shop.php status; exit ;;
	configure) wp eval-file /sandbox/shop.php configure; exit ;;
	refresh)
		wp eval '$o = wc_get_order(get_option("lulu_sandbox_last_order")); \printapp\functions\lulu\refresh_status($o);'
		wp eval-file /sandbox/shop.php status; exit ;;
	order)
		wp eval-file /sandbox/shop.php order
		wp action-scheduler run --hooks=print_app_lulu_send_order
		wp eval-file /sandbox/shop.php status; exit ;;
	up) ;;
	*) echo "usage: $0 [up|configure|order|status|refresh|down]"; exit 1 ;;
esac

started=$(date -u +%Y-%m-%dT%H:%M:%SZ)	# the logs keep earlier runs' (dead) addresses
docker compose up -d db wordpress tunnel

echo "Waiting for the tunnel address..."
url=""
for _ in $(seq 1 60); do
	url=$(docker compose logs --since "$started" tunnel 2>/dev/null | grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' | tail -1 || true)
	[ -n "$url" ] && break
	sleep 2
done
[ -n "$url" ] || { echo "No tunnel address. See: docker compose logs tunnel"; exit 1; }

echo "Waiting for WordPress..."
for _ in $(seq 1 60); do
	docker compose exec -T wordpress test -f /var/www/html/wp-config.php && break
	sleep 2
done
until wp db check >/dev/null 2>&1; do sleep 2; done

if ! wp core is-installed >/dev/null 2>&1; then
	if ! grep -q '^WP_ADMIN_PASSWORD=' .env.local; then
		printf '\n# local WordPress admin (user: admin)\nWP_ADMIN_PASSWORD=%s\n' "$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 20)" >> .env.local
		load_env
	fi
	wp core install --url="$url" --title="Print.App Lulu sandbox" --admin_user=admin \
		--admin_password="$WP_ADMIN_PASSWORD" --admin_email=admin@example.com --skip-email
	wp plugin install woocommerce --activate
fi

# the tunnel address changes on every start
wp option update home "$url" >/dev/null
wp option update siteurl "$url" >/dev/null
wp plugin activate printapp >/dev/null
wp eval-file /sandbox/shop.php configure

echo
echo "Shop:  $url   (admin: $url/wp-admin, user admin, password in .env.local)"
