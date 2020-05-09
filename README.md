How to integrate Drupal Commerce with 2Checkout
-------------------------------------------

### Commerce Settings:

1. [Install](https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules) this module.
2. Sign in to your Drupal admin
3. Under **Store** click **Configure Store**.
4. Click **Payment Methods**
5. Click **2checkout** and add action "Enable payment method: 2checkout"
6. Under **Account Number** enter your 2Checkout account number.
7. Under Secret Word enter your **Secret Word** (Must be the same value set on 2Checkout site management page.)
8. Select your checkout page language.
9. Select **Demo** to place demo sales or do not select for live sales. (If you are testing on a demo account, make sure you have your Site URL setup in your 2Checkout application. The return process will not complete if the domain your are testing on does not match the domain regestered to your 2Checkout account.)
10. Select **One Page Checkout** to use your custom single page checkout.
11. Select Third Party Cart to use third party cart parameters. Unselect to use Pass Through Products (Lists lineitem prices at 2Checkout).
12. Select **Log notifications during processing**.
13. Click **Save**.

### 2Checkout Settings:

1. Sign in to your 2Checkout account
2. Click the **Account** tab and **Site Management** subcategory.
3. Under **Direct Return** select **Header Redirect**.
4. Under **Secret Word** enter your Secret Word (Must be the same value entered in your Drupal Commerce admin.)
5. Click **Save Changes**.

NOTE: If you use the sandbox mode, take into account that you won't be
redirected back to your site because the `x_receipt_link_url` parameter
only works on live accounts:

- http://help.2checkout.com/articles/FAQ/Can-I-specify-the-return-URL-on-the-fly/

**Please contact 2Checkout directly with any integration questions.**
