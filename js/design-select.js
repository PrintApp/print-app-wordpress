/* global pa_admin_values api_key and product_id */

// Add error handling for better user feedback
(async function() {
    if (typeof pa_admin_values === 'undefined') return;

    const padLoadData = () => {
        return new Promise(async (resolve, reject) => {
            const request = new XMLHttpRequest();

            request.onreadystatechange = function() {
                if (request.readyState == 4) {
                    if (request.status == 200) 
                        resolve(JSON.parse(request.responseText));
                    else {
                        console.error('Error loading data:', request.responseText);
                        reject(request.responseText);
                    }
                }
            };
            request.open('GET', `https://run.print.app/${pa_admin_values.domain_key}/${pa_admin_values.product_id}/wp/admin`);
            request.send();
        });
    };

    const element = document.getElementById('print_app_tab');
    if (!element) return;

    try {
        const designContent = await padLoadData();
        if (!designContent || !designContent.html) {
            element.innerHTML = '<div class="print-app-error">Error loading design</div>';
            return;
        }

        let productTitle = encodeURIComponent(pa_admin_values.product_title || '');
        designContent.html = designContent.html.replace(/(href=".+?")/, `$1${productTitle}`);
        element.innerHTML = designContent.html;
    } catch (error) {
        element.innerHTML = '<div class="print-app-error">Failed to load design content</div>';
    }
})();
