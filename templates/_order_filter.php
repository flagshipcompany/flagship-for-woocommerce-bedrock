<select name="<?php echo $fieldName ?>" id="dropdown_exported_flagship">
  <option value="">
    <?php esc_html_e('FlagShip status', 'flagship-shipping-extension-for-woocommerce'); ?>
  </option>
  <option value="yes" <?php if ($value == 'yes'){ echo 'selected'; } ?>>
    <?php esc_html_e('exported', 'flagship-shipping-extension-for-woocommerce'); ?>
  </option>
  <option value="no" <?php if ($value == 'no'){ echo 'selected'; } ?>>
    <?php esc_html_e('not exported', 'flagship-shipping-extension-for-woocommerce'); ?>
  </option>
</select>
