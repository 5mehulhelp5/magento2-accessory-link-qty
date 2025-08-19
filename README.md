Installation
Composer Installation
composer require in-session/module-accessory-link-qty

Manual Installation
Create the following directory structure in your Magento installation:

app/code/InSession/AccessoryLinkQty
Download the module code and place it in the directory

Enable the module:

bin/magento module:enable InSession_AccessoryLinkQty
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
