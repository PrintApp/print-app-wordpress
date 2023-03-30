=== Plugin Name ===
Contributors: Print.App
Tags: customizer, photo album, print shop, web2print, gift print, diy print, product customizer, web-to-print, print software, print solution, HTML5 WYSIWYG, t-shirt designer, wysiwyg print editor, business card
Requires at least: 3.8
Tested up to: 6.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Print.App is a Web2Print plugin solution that provides an easy to use interface for creating artworks for prints like Business Card, TShirt, Banners. A beautiful web based print customization app for your online store. Integrates with WooCommerce.

== Description ==

PrintApp is a plugin solution that runs on WordPress + WooCommerce as a Software service providing your clients the ability to create their designs on the fly. It basically provides printing clients an easy to use WYSIWYG (What you see is what you get) “Do it yourself” interface for creating artworks for print.

It is an HTML5 based solution that allows you to create templates for products like Business Card, TShirt, Banners, Phone Templates etc.

This solution is fully based on pre-designed templates. Design templates are created in the editor which are then loaded by individual clients based on taste and choice, then modified to fit their needs and requirements. Based on our studies, it is far easier for majority of clients to edit an existing design template than create a whole design artwork from scratch especially for people with little background in graphics. In addition, it significantly reduces the overall time frame a client spends from landing on your site to placing an order.

The plugin allows your site to connect to our servers, loading the app tool for your users to create with. What's more.. it's Free and you can integrate in minutes.

Please learn more about this service from our site: [print.app](https://print.app)

== Installation ==

= Minimum Requirements =

* WordPress 3.8 or greater
* PHP version 5.2.4 or greater
* MySQL version 5.0 or greater
* WooCommerce 3.0.*

This plugin requires you to have WooCommerce installed. You can download [WooCommerce here:](http://www.woothemes.com/woocommerce) or install via the plugins section of your WordPress installation.

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of PrintApp, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "PrintApp" and click Search Plugins. Once you’ve found our plugin, you can view details about it such as the the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Configuration =

1. Using an FTP application like FileZilla, login to your server and change the following folder permissions to 777 (ensure you set every folder / file listed here):
	* plugins/PrintApp/system/settings.php
	* plugins/PrintApp/uploader/files
	* plugins/PrintApp/uploader/files/thumbnail

2. Next, you need to install PrintApp API Key. On the left side of the admin menu, you should find "PrintApp" link. Click it and in the admin page, you will find a link labelled "PLEASE INSTALL PrintApp APIKEY".
3. Generate and supply the API Key from [our site:](https://admin.PrintApp.io/domains)
4. Submit the form and once complete, please delete the install folder as instructed.
5. To administer a product, go to Products section in the admin and click the "Add Product" link.
6. There in the Product Data section, you will find "PrintApp" design template option; select your desired template to assign.

Once an order is placed and a Web2Print design is customized, the order details includes all the PrintApp details, like high resolution image files, the link to load the project as well as link to download the PDF file. If you do not find these, kindly check to see that the design has these options set to render in the design template section.

= Updating =

After updating, you may need to check your PrintApp tab again enter in your API and Secret keys.


== Frequently Asked Questions ==

= Does it work on Pad? =

Yes it does work on iPad and tablets. It's built on HTML5.

= How do I get support? =

We provide support via our [Slack portal:](http://slack.print.app) where you get to make suggestions, discuss with other users on the forum report any bug as well as request a support to getting your store properly working.

= Does the product come with templates and clipart images? =

The product comes with few templates and cliparts. However, you are advised to purchase your own library to suit your client base and product needs. Also, there's a marketplace where designers get to share template ideas. You can start from there and pick your choice as well as create and share with others.

= I have an existing shop. Can I still install PrintApp on it? =

Absolutely. You can install it over your existing OpenCart, WordPress, PrestaShop, Shopify or personal custom site without doing a fresh cart installation.

= Where will our files be hosted? =

Print.App is a Software as Service platform with dedicated servers for processing and storage. Your Picture and PDF files are stored on Amazon S3 storage servers. Design files are stored on our dedicated SSD based servers for swift random access and you can request for your files at anytime.
You can also use our Runtime API service to connect to the server and download any of your files with proper authentication.

= Where can I get more information? =

Check out our [website for more details](https://print.app)

== Screenshots ==

1. Editor Application.
2. Admin pictures manager.
3. Admin theme manager.
4. Admin settings.

== Changelog ==

= 1.0.2 =
Plugin name update
= 1.0.1 =
Domain update
= 1.0.0 =
First release.