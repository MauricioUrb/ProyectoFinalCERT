<?php
/*
 * @file
 * Contains \Drupal\pruebas_test\Form\PruebasTestForm
 */
namespace Drupal\pruebas_test\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/*
 *
 */
class PruebasTestForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'pruebas_test_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;

    $form['textfield'] = array(
      '#type' => 'textfield',
      '#title' => 'Textfield',
      '#required' => TRUE,
      '#size' => 1000,
    );

    $form['textarea'] = array(
      '#title' => t('Textarea'),
      '#type' => 'textarea',
      '#required' => TRUE,
    );

    $form['checkbox'] = array(
      '#title' => t('Checkbox'),
      '#type' => 'checkbox',
    );

    $form['date'] = array(
      '#type' => 'date',
      '#title' => t('Date'),
    );

    $form['fieldset'] = array(
      '#type' => 'fieldset',
      '#title' => t('fieldset'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      //'#weight' => 5,
    );

    $form['file'] = array(
      '#type' => 'file',
      '#title' => t('file'),
    );

    $form['machine_name'] = array(
      '#type' => 'machine_name',
      '#title' => t('machine_name'),
    );/*

    $form['managed_file'] = array(
      '#type' => 'managed_file',
      '#title' => t('managed_file'),
      '#description' => t('The uploaded image will be displayed on this page using the image style choosen below.'),
      '#default_value' => variable_get('image_example_image_fid', ''),
      '#upload_location' => 'public://image_example_images/',
    );*/

    $form['password'] = array(
      '#type' => 'password',
      '#title' => t('password'),
    );

    $form['password_confirm'] = array(
      '#type' => 'password_confirm',
      '#title' => t('password_confirm'),
    );

    $active = array(0 => t('Closed'), 1 => t('Active'));
    $form['radios'] = array(
      '#type' => 'radios',
      '#title' => t('radios'),
      '#default_value' => isset($node->active) ? $node->active : 1,
      '#options' => $active,
      '#description' => t('When a poll is closed, visitors can no longer vote for it.'),
      '#access' => $admin,
    );
    /*No hay ejemplo
    $form['radio'] = array(
      '#type' => 'radio',
      '#title' => t('radios'),
    );*/

    $form['select'] = array(
      '#type' => 'select',
      '#title' => t('select'),
      '#options' => array(
        0 => t('No'),
        1 => t('Yes'),
      ),
      '#default_value' => $category['selected'],
      '#description' => t('Set this to <em>Yes</em> if you would like this category to be selected by default.'),
    );/*

// Build the sortable table header.
  $header = array(
    'title' => array('data' => t('Title'), 'field' => 'n.title'),
    'type' => array('data' => t('Type'), 'field' => 'n.type'),
    'author' => t('Author'),
    'status' => array('data' => t('Status'), 'field' => 'n.status'),
    'changed' => array('data' => t('Updated'), 'field' => 'n.changed', 'sort' => 'desc')
  );
...
//Get the node data.
  $nids = $query
    ->fields('n',array('nid'))
    ->limit(50)
    ->orderByHeader($header)
    ->execute()
    ->fetchCol();
  $nodes = node_load_multiple($nids);
...
//Build the rows.
  $options = array();
  foreach ($nodes as $node) {
...
    $options[$node->nid] = array(
      'title' => array(
        'data' => array(
          '#type' => 'link',
          '#title' => $node->title,
          '#href' => 'node/' . $node->nid,
          '#options' => $l_options,
          '#suffix' => ' ' . theme('mark', array('type' => node_mark($node->nid, $node->changed))),
        ),
      ),
      'type' => check_plain(node_type_get_name($node)),
      'author' => theme('username', array('account' => $node)),
      'status' => $node->status ? t('published') : t('not published'),
      'changed' => format_date($node->changed, 'short'),
    );
   //For simplicity, this example omits the code to set the operations column.
...
    $form['tableselect'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No content available.'),
    );*/

    $form['text_format'] = array(
      '#type' => 'text_format',
      '#title' => t('text_format'),
    );

    $form['vertical_tabs'] = array(
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-tab2'
    );

    $form['weight'] = array(
      '#type' => 'weight',
      '#title' => t('weight'),
      '#delta' => 15,
    );

    /*/Special elements
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );
    $form['actions']['delete'] = array(
      '#type' => 'button',
      '#value' => t('Delete'),
    );
    $form['actions']['cancel'] = array(
      '#markup' => l(t('Cancel'), 'foo/bar'),
    );*/

    $form['button'] = array(
      '#type' => 'button',
      '#value' => t('Preview'),
    );
    /*No funciono
    $form['container'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array(
          'field-type-' . drupal_html_class($field['type']),
          'field-name-' . drupal_html_class($field_name),
          'field-widget-' . drupal_html_class($instance['widget']['type']),
       ),
    );
    */
    /*No hay ejemplo
    $form['image_button'] = array(
      '#type' => 'image_button',
      '#value' => ?,
    );

    $form['form'] = array(
      '#type' => 'form',
      '#title' => t('form'),
    );*/

    $form['hidden'] = array(
      '#type' => 'hidden',
      '#value' => t('Value'),
    );

    /*No hay ejemplo
    $form['token'] = array(
      '#type' => 'token',
      '#value' => t('token'),
    );*/

    $form['markup'] = array(
      '#markup' => t('markup.'),
    );

    $form['item'] = array(
      '#type' => 'item',
      '#title' => t('item'),
    );

    $form['value'] = array(
      '#type' => 'value',
      '#value' => t('Value'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );
    return $form;
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}
