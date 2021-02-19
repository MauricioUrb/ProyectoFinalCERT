<?php
/*
 * @file
 * Contains \Drupal\editar_sitios\Form\EditarSitiosForm
 */
namespace Drupal\editar_sitios\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
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
  public function buildForm(array $form, FormStateInterface $form_state, $id_s = NULL){
    $node = \Drupal::routeMatch()->getParameter('node');
    $nid = $node->nid->value;
    //declaramos una variable global para poder usar en otra funcion
    global $vars;
    $vars = $id_s;

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
    $select->condition('s.id_sitios', $id_s);
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
    $form['dependencias'] = array(
      '#type' => 'textfield',
      '#title' => t('Dependencias'),
      '#size' => 100,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $result->nombre_dependencia,
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
      '#value' => t('Actualizar'),
    );
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
        global $vars;
        //se actualiza primero la tabla sitios
        // se hace la conexion a la base de datos
        $connection = \Drupal::service('database');
        // se realiza el update en la tabla hallazgos
        $update = $connection->update('sitios')
                // Se agregan actualizan todos los campos.
                ->fields(array(
//                      'nombre_sitios' => $form_state->getValue('nombre'),
                        'descripcion_sitios' => $form_state->getValue('description'),
                        'url_sitios' => $form_state->getValue('enlace'),
                ))
                //Se agregan condiciones
                ->condition('id_sitios', $vars)
                // ejecutamos el query
                ->execute();

        //después se actualiza la tabla ip donde el id de la ip este relacionado con el sitio
        //creamos un subquery con un select para obtener el id que necesitamos
        $subquery = $connection->select('ip_sitios', 'ips');
        //Se hace un join con tablas necesarias
        $subquery->join('sitios', 's', 's.id_sitios = ips.id_sitios');
        $subquery->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
        //Se especifican las columnas a leer
        $subquery->fields('ip', array('id_ip'));
        $subquery->condition('s.id_sitios', $vars);

        $select = $connection->update('dir_ip')
               ->fields(array('dir_ip_sitios' => $form_state->getValue('ip'),))
               ->condition('id_ip', $subquery)
               ->execute();

        //por último se actualiza la tabla dependencias donde el id de la dependencia este relacionada con el sitio
        //creamos un subquery con un select para obtener el id que necesitamos
        $subquery = $connection->select('dependencias_sitios', 'ds');
            //Se hace un join con tablas necesarias
        $subquery->join('sitios', 's', 's.id_sitios = ds.id_sitios');
        $subquery->join('dependencias', 'd', 'd.id_dependencia = ds.id_dependencia');
        //Se especifican las columnas a leer
        $subquery->fields('d', array('id_dependencia'));
        $subquery->condition('s.id_sitios', $vars);

        $select = $connection->update('dependencias')
               ->fields(array('nombre_dependencia' => $form_state->getValue('dependencias'),))
               ->condition('id_dependencia', $subquery)
               ->execute();

        // mostramos el mensaje de que se inserto
        $messenger_service = \Drupal::service('messenger');
        $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
  }
}