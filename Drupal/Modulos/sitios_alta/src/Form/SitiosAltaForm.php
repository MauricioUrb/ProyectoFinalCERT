<?php
/*
 * @file
 * Contains \Drupal\sitios_alta\Form\SitiosAltaForm
 */
namespace Drupal\sitios_alta\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/*
 *
 */
class SitiosAltaForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'sitios_alta_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    $form['nombre'] = array(
      '#type' => 'textfield',
      '#title' => 'Sitio.',
      '#required' => TRUE,
      '#size' => 1000,
    );
    $form['description'] = array(
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#description' => t('Descripción del sitio.'),
      '#required' => TRUE,
    );
    $form['ip'] = array(
      '#type' => 'textfield',
      '#title' => t('IP'),
      '#size' => 100,
      '#maxlength' => 128,
      '#required' => TRUE,
    );
    $form['dependecias'] = array(
      '#type' => 'textfield',
      '#title' => t('Dependencias'),
      '#size' => 100,
      '#maxlength' => 128,
      '#required' => TRUE,
    );
    $form['enlace'] = array(
      '#title' => t('URL'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 128,
      '#size' => 1000,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Dar de alta'),
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
