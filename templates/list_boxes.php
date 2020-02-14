      
<?php
  wp_enqueue_script('vuejs');
  wp_enqueue_script('package_boxes');
?>

<div id="flagship_package_boxes">
    <span class="hidden" ref="getBoxesUrl"><?php  echo $get_boxes_url; ?></span>
    <span class="hidden" ref="saveBoxesUrl"><?php  echo $save_boxes_url; ?></span>
    <div class="woocommerce-layout">
        <div class="woocommerce-layout__header">
            <h1 class="woocommerce-layout__header-breadcrumbs"></h1>
        </div>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Package boxes','flagship-woocommerce-extension'); ?></h1>
            <div v-show="data_saved" id="saved_message" class="notice notice-success"><p><strong>Package boxes have been saved.</strong></p></div>
            <div v-show="invalid_data" id="error_message" class="notice notice-error"><p><strong>Invalid submission. Please check all the fields.</strong></p></div>
            <div v-show="save_error" id="error_message" class="notice notice-error"><p><strong>Unable to save the boxes.</strong></p></div>
            <table class="wp-list-table widefat fixed striped posts">
                <thead>
                  <tr>
                      <th><strong><?php _e('Model name','flagship-woocommerce-extension'); ?></strong></th>
                      <th><strong><?php _e('Length (in)','flagship-woocommerce-extension'); ?></strong></th>
                      <th><strong><?php _e('Width (in)','flagship-woocommerce-extension'); ?></strong></th>
                      <th><strong><?php _e('Height (in)','flagship-woocommerce-extension'); ?></strong></th>
                      <th><strong><?php _e('Max supported weight (LB)','flagship-woocommerce-extension'); ?></strong></th>
                      <th><strong><?php _e('Extra charge ($)','flagship-woocommerce-extension'); ?></strong></th>
                      <th></th>
                  </tr>
                </thead>
                <tbody>
                    <tr v-for="(box, index) in boxes" v-bind:key="index" ref="box_list">
                        <td><input v-model.trim="box.model" v-bind:name=" 'flagship_boxes' + index + '_model' " v-bind:id=" 'flagship_boxes' + index + '_model' " type="text" style="width:100px;"></td>
                        <td><input v-model.number="box.length" v-bind:name=" 'flagship_boxes' + index + '_length' " v-bind:id=" 'flagship_boxes' + index + '_length' " type="number" style="width:50px;"></td>
                        <td><input v-model.number="box.width" v-bind:name=" 'flagship_boxes' + index + '_width' " v-bind:id=" 'flagship_boxes' + index + '_width' " type="number" style="width:50px;"></td>
                        <td><input v-model.number="box.height" v-bind:name=" 'flagship_boxes' + index + '_height' " v-bind:id=" 'flagship_boxes' + index + '_height' " type="number" style="width:50px;"></td>
                        <td><input v-model.number="box.max_weight" v-bind:name=" 'flagship_boxes' + index + '_max_weight' " v-bind:id=" 'flagship_boxes' + index + '_max_weight' " type="number" style="width:50px;"></td>
                        <td><input v-model.number="box.extra_charge" v-bind:name=" 'flagship_boxes' + index + '_extra_charge' " v-bind:id=" 'flagship_boxes' + index + '_extra_charge' " type="number" style="width:50px;"></td>
                        <td><button v-on:click="removeBox(box.id)" v-bind:name=" 'remove_' + box.id" class="button-link-delete">Remove </button></td>
                    </tr>
                    <tr ref="box_new">
                        <td><input v-model.trim="box_form.model" name="flagship_boxes_new_model" id="flagship_boxes_new_model" type="text" style="width:100px;"></td>
                        <td><input v-model.number="box_form.length" name="flagship_boxes_new_length" id="flagship_boxes_new_length" type="number" style="width:50px;"></td>
                        <td><input v-model.number="box_form.width" name="flagship_boxes_new_with" id="flagship_boxes_new_width" type="number" style="width:50px;"></td>
                        <td><input v-model.number="box_form.height" name="flagship_boxes_new_height" id="flagship_boxes_new_height" type="number" style="width:50px;"></td>
                        <td><input v-model.number="box_form.max_weight" name="flagship_boxes_new_max_weight" id="flagship_boxes_new_max_weight" type="number" style="width:50px;"></td>
                        <td><input v-model.number="box_form.extra_charge" name="flagship_boxes_new_extra_charge" id="flagship_boxes_new_extra_charge" type="number" style="width:50px;"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
              <button v-on:click="saveBoxes()" name="save" class="button-primary woocommerce-save-button" type="submit">Save </button>
            </p>
        </div>
    </div>
</div>