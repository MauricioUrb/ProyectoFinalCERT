<?php
/*
 * @file
 * Contains \Drupal\editar_sitios\Form\EditarSitiosForm
 */
namespace Drupal\editar_sitios\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/*
 *
 */
class EditarSitiosForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'editar_sitios_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state){
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;

    $select = Database::getConnection()->select('dependencias', 'd');
    //Se hace un join con tablas necesarias
    $select ->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
    $select ->join('sitios', 's', 's.id_sitios = ds.id_sitios');
    $select ->join('ip_sitios', 'ips', 's.id_sitios = ips.id_sitios');
    $select ->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
    //Se especifican las columnas a leer
    $select->fields('s', array('id_sitios', 'descripcion_sitios', 'url_sitios'))
           ->fields('d', array('nombre_dependencia'))
           ->fields('ip', array('dir_ip_sitios'));
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){
/*    $form['nombre'] = array(
      '#type' => 'textfield',
      '#title' => 'Sitio.',
      '#required' => TRUE,
      '#size' => 1000,
      '#default_value' => $result->
    );*/
    $form['description'] = array(
      '#title' => t('Description'),
      '#type' => 'textarea',
      '#description' => t('Descripción del sitio.'),
      '#required' => TRUE,
      '#default_value' => $result->descripcion_sitios,
    );
    $form['ip'] = array(
      '#type' => 'textfield',
      '#title' => t('IP'),
      '#size' => 100,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $result->dir_ip_sitios,
    );
    $form['dependecias'] = array(
      '#type' => 'textfield',
      '#title' => t('Dependencias'),
      '#size' => 100,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $result->nombre_dependencia
    );
    $form['enlace'] = array(
      '#title' => t('URL'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#maxlength' => 128,
      '#size' => 1000,
      '#default_value' => $result->url_sitios,
    );
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Dar de alta'),
    );
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
          // se hace la conexion a la base de datos
          $connection = \Drupal::service('database');
          // se realiza el update en la tabla hallazgos
          $update = $connection->update('sitios')
                  // Se agregan actualizan todos los campos.
                  ->fields(array(
                          'nombre_hallazgo_vulnerabilidad' => $form_state->getValue('nombre'),
                          'descripcion_hallazgo' => $form_state->getValue('description'),
                          'solucion_recomendacion_halazgo' => $form_state->getValue('solution'),
                          'referencias_hallazgo' => $form_state->getValue('references'),
                          'r_ejecutivo_hallazgo' => $form_state->getValue('resumen_ejecutivo'),
                          'recomendacion_general_hallazgo' => $form_state->getValue('recomendation'),
                  ))
                  //Se agregan condiciones
                  ->condition('id_hallazgos', 3)
                  // ejecutamos el query
                  ->execute();
          // mostramos el mensaje de que se inserto
          $messenger_service = \Drupal::service('messenger');
          $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
  }
}
