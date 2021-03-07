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
use Drupal\Core\Link;

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
    global $hall_arr;
    global $cantidadImg;
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
      $hall_arr = $hallazgos;
      
      $form['hallazgos'] = array(
        '#type' => 'select',
        '#title' => t('Selecciona el hallazgo a agregar:'),
        '#options' => $hallazgos,
        '#required' => TRUE,
      );
      $cantidadImg = 5;
      Database::setActiveConnection();
    }else{
      $pos = 0;
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $hallazgos = $select->execute()->fetchCol();
      $hall_arr = $hallazgos;
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $select->condition('id_hallazgo',$hall_id);
      $nombre_hallazgo = $select->execute()->fetchCol();
      foreach ($hallazgos as $hallazgo) {
        if($hallazgo == $nombre_hallazgo[0]){break;}
        $pos++;
      }
      $form['hallazgos'] = array(
        '#type' => 'select',
        '#title' => t('Selecciona el hallazgo a editar:'),
        '#options' => $hallazgos,
        '#required' => TRUE,
        '#default_value' => $pos,
      );
      //Obtener el id_rev_sitio_hall para 
      $consulta = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $consulta->fields('h', array('id_rev_sitio_hall'));
      $consulta->condition('id_rev_sitio',$rev_id);
      $consulta->condition('id_hallazgo',$hall_id);
      $id_rev_sitio_hall = $consulta->execute()->fetchCol();
      Database::setActiveConnection();
      //Cantidad de imágenes ya agregadas
      $consulta = Database::getConnection()->select('file_managed', 'f');
      $consulta->addExpression('MAX(id_rev_sh)','file_managed');
      $resultado = $consulta->execute()->fetchCol();
      $cantidadImg = 5 - $resultado[0];
    }
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
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
    //Imágemes
    for($i = 1; $i <= $cantidadImg; $i++){
      $form['grupo'.$i] = array(
        '#type' => 'fieldset',
        '#collapsible' => TRUE, 
        '#collapsed' => FALSE,
      );
      $form['grupo'.$i]['img'.$i] =array(
        '#type' => 'managed_file',
        '#title' => 'Evidencia ' . $i,
        '#description' => t('Debe ser formato: jpg, jpeg, png'),
        '#upload_validators' => array(
              //se valida que solo sean archivos de imagen
              'file_validate_extensions' => array('jpg jpeg png'),
              //se limita su tamaño a 100MB
              'file_validate_size' => array(1024 * 1024 * 100),
        ),
        '#upload_location' => 'public://content/evidencia',
      );
      $form['grupo'.$i]['description'.$i] = array(
        '#type' => 'textfield',
        '#title' => 'Descripcion evidencia ' . $i,
        '#size' => 100,
        '#maxlength' => 100,
      );
    }
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
    $url = Url::fromRoute('edit_revision.content', array('rev_id' => $rev_id));
    $project_link = Link::fromTextAndUrl('Cancelar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($project_link),);
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
          'estatus' => 1,
        ))->execute();
      }else{
        $mensaje = 'Hallazgo actualizado.';
        $result = $connection->update('revisiones_hallazgos')
        ->fields(array(
          'id_hallazgo' => $id_hallazgo[0],
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
    //Obtener el id_rev_sitio_hall
    $consulta = Database::getConnection()->select('revisiones_hallazgos', 'h');
    $consulta->fields('h', array('id_rev_sitio_hall'));
    $consulta->condition('id_rev_sitio',$id_principal);
    $consulta->condition('id_hallazgo',$id_hallazgo[0]);
    $id_rev_sitio_hall = $consulta->execute()->fetchCol();
    Database::setActiveConnection();
    //Obtencion de las imagenes del formulario
    for($i = 1; $i <= $cantidadImg; $i++){
      $form_file = $form_state->getValue(['grupo'.$i]['imagen'.$i], 0);
      if($form_file){
        $file = File::load($form_file[0]);
        //se guarda en la base de datos file_managed
        $file->setPermanent();
        $file->save();
        //extraer el nombre del archivo subido
        $file_name = $file->getFilename();
        //se hace update de esa tabla para agregar referencia a la tabla revisiones
        $update = $connection->update('file_managed')
                ->fields(array(
                    'id_rev_sh' => $id_rev_sitio_hall[0],
                    'descripcion' => $form_state->getValue(['grupo'.$i]['description'.$i]),
                ))
                ->condition('filename', $file_name)
                ->execute();
        }
    }
    //Inserción en la bd de imagenes
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
  	$form_state->setRedirectUrl(Url::fromRoute('edit_revision.content', array('rev_id' => $regresar)));
  }
}
