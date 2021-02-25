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

  public function getFormId(){
    return 'editar_sitios_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id_s = NULL, $id_ip = NULL, $id_dep = NULL){
    //declaramos una variable global para poder usar en otra funcion
    global $vars, $dependencia_id, $ip_id;
    $vars = $id_s;
    $dependencia_id = $id_dep;
    $ip_id = $id_ip;

    //conectar a la otra db
    \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
    $connection = \Drupal\Core\Database\Database::getConnection();

    $select = Database::getConnection()->select('dependencias', 'd');
    //Se hace un join con tablas necesarias
    $select ->join('dependencias_sitios', 'ds', 'd.id_dependencia = ds.id_dependencia');
    $select ->join('sitios', 's', 's.id_sitio = ds.id_sitio');
    $select ->join('ip_sitios', 'ips', 's.id_sitio = ips.id_sitio');
    $select ->join('dir_ip', 'ip', 'ip.id_ip = ips.id_ip');
    //Se especifican las columnas a leer
    $select->fields('s', array('id_sitio', 'descripcion_sitio', 'url_sitio'))
           ->fields('d', array('nombre_dependencia'))
           ->fields('ip', array('dir_ip_sitios'));
    //condiciones para mostrar solo los datos requeridos
    $select->condition('s.id_sitio', $id_s);
    $select->condition('d.id_dependencia', $id_dep);
    $select->condition('ip.id_ip', $id_ip);
    //Se realiza la consulta
    $results = $select->execute();

    $txt = '';
    //se recorren los resultados para después imprimirlos
    foreach ($results as $result){

      $form['description'] = array(
        '#title' => t('Description'),
        '#type' => 'textarea',
        '#description' => t('Descripción del sitio.'),
        '#required' => TRUE,
        '#default_value' => $result->descripcion_sitio,
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
        '#default_value' => $result->url_sitio,
      );
      $form['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Actualizar'),
      );
    }
    return $form;
    //regresar a la default
    \Drupal\Core\Database\Database::setActiveConnection();
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
        global $vars, $dependencia_id, $ip_id;
        //se actualiza primero la tabla sitios
        // se hace la conexion a la base de datos
	      \Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
	      $connection = \Drupal\Core\Database\Database::getConnection();

        // se realiza el update en la tabla sitios
        $update = $connection->update('sitios')
                // Se agregan actualizan todos los campos.
                ->fields(array(
//                      'nombre_sitios' => $form_state->getValue('nombre'),
                        'descripcion_sitio' => $form_state->getValue('description'),
                        'url_sitio' => $form_state->getValue('enlace'),
                ))
                //Se agregan condiciones
                ->condition('id_sitio', $vars)
                // ejecutamos el query
                ->execute();

        //después se actualiza la tabla ip donde el id de la ip este relacionado con el sitio
        $select = $connection->update('dir_ip')
               ->fields(array('dir_ip_sitios' => $form_state->getValue('ip'),))
//               ->condition('id_ip', $subquery)
	       ->condition('id_ip', $ip_id)
               ->execute();

        //por último se actualiza la tabla dependencias donde el id de la dependencia este relacionada con el sitio
        $select = $connection->update('dependencias')
               ->fields(array('nombre_dependencia' => $form_state->getValue('dependencias'),))
//               ->condition('id_dependencia', $subquery)
	       ->condition('id_dependencia', $dependencia_id)
               ->execute();

        // mostramos el mensaje de que se inserto
        $messenger_service = \Drupal::service('messenger');
        $messenger_service->addMessage(t('Se ha actualizado la base de datos'));
	//regresar a la default
	\Drupal\Core\Database\Database::setActiveConnection();
  
  }
}
