<?php
/*
 * @file
 * Contains \Drupal\asignacion_revisiones\Form\AsignacionRevisionesForm
 */
namespace Drupal\asignacion_revisiones\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/*
 *
 */
class AsignacionRevisionesForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'asignacion_revisiones_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;

    //Tipo de revision
    $active = array(0 => t('Oficio'), 1 => t('Circular'));
    $form['tipo'] = array(
      '#type' => 'radios',
      '#title' => t('Tipo de revisión'),
      '#default_value' => isset($node->active) ? $node->active : 0,
      '#options' => $active,
    );
    
    //Coneccion a la BD
    $node = \Drupal::routeMatch()->getParameter('node');
    //Se selecciona la tabla en modo lectura
    $select = Database::getConnection()->select('users_field_data', 'r');
    //Se especifican las columnas a leer
    $select->fields('r', array('name'));
    //WHERE (este caso es una agrupacion por and)
    $consulta = $select->andConditionGroup()
      ->condition('name', '', '<>')
      ->condition('name', 'admin', '<>');
    $select->condition($consulta);
    //Se realiza la consulta
    $results = $select->execute()->fetchCol();

    //Se crean las opciones para los revisores
    $form['revisores'] = array(
      '#title' => t('Asignar reviores:'),
      '#type' => 'checkboxes',
      '#options' => $results,
      '#required' => TRUE,
    );

    //Estatus de la revision
    $form['estatus'] = array(
      '#title' => t('Estatus de la revisi  n:'),
      '#type' => 'radios',
      '#options' => array(0 => t('Asignado'), 1 => t('En proceso'), 2 => t('Concluido'), 3 => t('Apro$      '#default_value' => isset($node->active) ? $node->active : 0,
    );

    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Asignar revisión'),
    );
    return $form;
  }
  /*
   * (@inheritdoc)
   * Validacion de los datos ingresados
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //Validacion de prueba
    if($form_state->getValue(['tipo']) == 0){
      $form_state->setErrorByName('revisores',t('Mensaje a desplegar.'));
    }
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage(t('The form is working.'));
  }
}