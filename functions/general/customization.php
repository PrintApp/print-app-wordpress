<?php

    namespace printapp\functions\general;

    /**
     * Customization storage.
     *
     * As of 2.3.0 the customization payload is stored in the WooCommerce
     * customer session (WC()->session) instead of a transient keyed by a
     * self-managed token.
     *
     * Why: the previous transient + self-managed cookie/token + native PHP
     * $_SESSION backup could leak across customers on sites with full-page
     * caching (a cached Set-Cookie header — the guest token or PHPSESSID —
     * is replayed to every visitor, so they share one storage key). That is
     * the cause of "customer 2 gets customer 1's artwork in the cart".
     * WC()->session is keyed by WooCommerce's own session cookie, which page
     * caches are already configured to bypass, and is scoped per customer by
     * design — so we no longer need to invent a per-user token at all.
     */

    /**
     * Return the WooCommerce session handler, or null if unavailable.
     *
     * @param bool $create When true, force the customer session cookie to be
     *                     set so the data persists across requests. Only do
     *                     this on writes — forcing it on every read would set
     *                     a WC session cookie for every product-page visitor
     *                     and hurt full-page cache hit rates site-wide.
     */
    function pa_get_session($create = false) {
        if (!function_exists('WC')) {
            return null;
        }
        $wc = WC();
        if (!$wc || !isset($wc->session) || !$wc->session) {
            return null;
        }
        if ($create && !$wc->session->has_session()) {
            $wc->session->set_customer_session_cookie(true);
        }
        return $wc->session;
    }

    function pa_session_key($product_id) {
        return 'pa_customization_' . absint($product_id);
    }

    function save_customization_data($product_id, $customization_data) {
        $product_id = absint($product_id);
        $customization_data = wp_unslash($customization_data);

        $session = pa_get_session(true);
        if (!$session) {
            return false;
        }

        $key = pa_session_key($product_id);
        $session->set($key, $customization_data);

        return $key;
    }

    function get_customization_data($product_id) {
        $product_id = absint($product_id);

        $session = pa_get_session(false);
        if (!$session) {
            return false;
        }

        $data = $session->get(pa_session_key($product_id), false);
        if ($data === false || empty($data)) {
            return false;
        }

        return $data;
    }

    function delete_customization_data($product_id) {
        $product_id = absint($product_id);

        $session = pa_get_session(false);
        if ($session) {
            $session->__unset(pa_session_key($product_id));
        }

        return true;
    }
