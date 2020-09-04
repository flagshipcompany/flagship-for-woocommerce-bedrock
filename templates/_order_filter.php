<select name="<?php echo $fieldName ?>" id="dropdown_exported_flagship">
  <option value="">
    <?php esc_html_e('FlagShip status', 'flagship-woocommerce-extension'); ?>
  </option>
  <option value="yes" <?php if ($value == 'yes'){ echo 'selected'; } ?>>
    <?php esc_html_e('exported', 'flagship-woocommerce-extension'); ?>
  </option>
  <option value="no" <?php if ($value == 'no'){ echo 'selected'; } ?>>
    <?php esc_html_e('not exported', 'flagship-woocommerce-extension'); ?>
  </option>
</select>
