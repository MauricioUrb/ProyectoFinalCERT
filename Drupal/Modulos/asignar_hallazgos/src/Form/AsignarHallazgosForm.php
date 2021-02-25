<?php
/*
 * @file
 * Contains \Drupal\asignar_hallazgos\Form\AsignarHallazgosForm
 */
namespace Drupal\asignar_hallazgos\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/*
 *
 */
class AsignarHallazgosForm extends FormBase{
/*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'asignar_hallazgos_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL, $id_rev_sitio = NULL, $hall_id = NULL){
    global $id_principal;
    $id_principal = $id_rev_sitio;
    global $regresar;
    $regresar = $rev_id;
    global $id_hall;
    $id_hall = $hall_id;
    //Consulta de la URL del sitio para imprimirlo
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('sitios',"s","r.id_sitio = s.id_sitio");
    $select->fields('s', array('url_sitio'));
    $select->condition('id_rev_sitio',$id_rev_sitio);
    $activo = $select->execute()->fetchCol();
    $form['sitio'] = array(
      '#type' => 'item',
      '#title' => t('Activo'),
      '#markup' => $activo[0],
    );
    if($hall_id == 0){
      //Traemos todas las opciones de hallazgos/vulnerabilidades para que los seleccione
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->fields('h', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$id_rev_sitio);
      $hallazgos_no = $select->execute()->fetchCol();
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      if(sizeof($hallazgos_no)){
        $select->condition('id_hallazgo',$hallazgos_no,'NOT IN');
      }
      $hallazgos = $select->execute()->fetchCol();
      global $hall_arr;
      $hall_arr = $hallazgos;
      
      $form['hallazgos'] = array(
        '#type' => 'select',
        '#title' => t('Selecciona el hallazgo a agregar:'),
        '#options' => $hallazgos,
        '#required' => TRUE,
      );
    }else{
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $select->condition('id_hallazgo',$hall_id);
      $nombre_hallazgo = $select->execute()->fetchCol();
      $form['hallazgos'] = array(
        '#type' => 'item',
        '#title' => t('Editar hallazgo:'),
        '#markup' => $nombre_hallazgo[0],
      );
    }
    //Se obtienen valores si ya existen
    $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $select->fields('h', array('descripcion_hall_rev'));
    $select->fields('h', array('recursos_afectador'));
    $select->fields('h', array('impacto_hall_rev'));
    $select->fields('h', array('cvss_hallazgos'));
    $select->condition('id_rev_sitio',$id_rev_sitio);
    $select->condition('id_hallazgo',$hall_id);
    $results = $select->execute();
    Database::setActiveConnection();
    $descripcion_hall_rev = '';
    $recursos_afectador = '';
    $impacto_hall_rev = '';
    $cvss_hallazgos = '';
    foreach ($results as $result) {
      $descripcion_hall_rev = $result->descripcion_hall_rev;
      $recursos_afectador = $result->recursos_afectador;
      $impacto_hall_rev = $result->impacto_hall_rev;
      $cvss_hallazgos = $result->cvss_hallazgos;
    }
    $form['descripcion'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#required' => TRUE,
      '#default_value' => $descripcion_hall_rev,
    );
    $form['recursos'] =array(
      '#type' => 'textarea',
      '#title' => 'Recursos afectados',
      '#required' => TRUE,
      '#default_value' => $recursos_afectador,
    );
    $form['impacto'] =array(
      '#type' => 'textfield',
      '#title' => 'Impacto',
      '#required' => TRUE,
      '#default_value' => $impacto_hall_rev,
    );
    $form['cvss'] = array(
      '#type' => 'textfield',
      '#title' => 'CVSS',
      '#required' => TRUE,
      '#default_value' => $cvss_hallazgos,
    );
    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Guardar'),
    );
    return $form;    
  }
  /*
   * (@inheritdoc)
   * Validacion de los datos ingresados
   */
  public function validateForm(array &$form, FormStateInterface $form_state){
    //Verificacion de la cadena del CVSS
    $formato = preg_split('/AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?/', $form_state->getValue(['cvss']));
    foreach($formato as $valido){
      if($valido != ''){
        $form_state->setErrorByName('cvss','La cadena ingresada de CVSS no tiene el formato correcto.');}
    }
    //Verificacion valor del impacto
    $cvss = explode(".",$form_state->getValue(['impacto']));
    $rango = 0;
    if(sizeof($cvss) != 2){$rango = 1;}
    elseif (strlen($cvss[1]) != 1){$rango = 1;}
    elseif(strlen($cvss[0]) == 2 && $cvss[0] != 10){$rango = 1;}
    elseif(strlen($cvss[0]) == 2 && $cvss[1] != 0){$rango = 1;}
    elseif (strlen($cvss[0]) == 1){
      if($cvss[0] < 0 || $cvss[0] > 9){$rango = 1;}
    }
    elseif($cvss[1] < 0 || $cvss[1] > 9){$rango = 1;}
    if($rango){
      $form_state->setErrorByName('impacto','El valor del impacto sólo puede ir del rango 0.0. a 10.0');
    }
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $hall_arr;
    global $id_principal;
    global $regresar;
    global $id_hall;
    $mensaje = 'Hallazgo agregado a la revision.';
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //Obtener el id_hallazgo
    $consulta = Database::getConnection()->select('hallazgos', 'h');
    $consulta->fields('h', array('id_hallazgo'));
    $consulta->condition('nombre_hallazgo_vulnerabilidad',$hall_arr[$form_state->getValue(['hallazgos'])]);
    $id_hallazgo = $consulta->execute()->fetchCol();
    //Insercion en la BD
    if($id_hall == 0){
      $result = $connection->insert('revisiones_hallazgos')
        ->fields(array(
          'id_rev_sitio' => $id_principal,
          'id_hallazgo' => $id_hallazgo[0],
          'descripcion_hall_rev' => $form_state->getValue(['descripcion']),
          'recursos_afectador' => $form_state->getValue(['recursos']),
          'impacto_hall_rev' => $form_state->getValue(['impacto']),
          'cvss_hallazgos' => $form_state->getValue(['cvss']),
        ))->execute();
      }else{
        $mensaje = 'Hallazgo actualizado.';
        $result = $connection->update('revisiones_hallazgos')
        ->fields(array(
          'descripcion_hall_rev' => $form_state->getValue(['descripcion']),
          'recursos_afectador' => $form_state->getValue(['recursos']),
          'impacto_hall_rev' => $form_state->getValue(['impacto']),
          'cvss_hallazgos' => $form_state->getValue(['cvss']),
        ))
        ->condition('id_hallazgo', $id_hall)
        ->condition('id_rev_sitio', $id_principal)
        ->execute();
      }
    $result = $connection->update('revisiones')
      ->fields(array(
        'id_estatus' => 2,
      ))
      ->condition('id_revision', $regresar)
      ->execute();
    Database::setActiveConnection();
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	$form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
  }
}