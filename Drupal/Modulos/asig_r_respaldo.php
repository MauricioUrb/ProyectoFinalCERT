<?php
/*
 * @file
 * Contains \Drupal\asignacion_revisiones\Form\AsignacionRevisionesForm
 */
namespace Drupal\asignacion_revisiones\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
///
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Shared\Converter;
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
    $current_user_roles = \Drupal::currentUser()->getRoles();
    $rol = TRUE;
    if (in_array('coordinador de revisiones', $current_user_roles) || in_array('auxiliar', $current_user_roles)){
      $rol = FALSE;
    }
    $grupo = TRUE;
    if(!$rol){
      if(in_array('sistemas', $current_user_roles) || in_array('auditoria', $current_user_roles)){$grupo = FALSE;}
    }
    if($rol || $grupo){
      return array('#markup' => "No tienes permiso para ver este formulario.",);
    }
    //Tipo de revision
    $active = array(0 => t('Oficio'), 1 => t('Circular'));
    $form['tipo'] = array(
      '#type' => 'radios',
      '#title' => t('Tipo de revisión'),
      '#default_value' => isset($node->active) ? $node->active : 0,
      '#options' => $active,
    );

    //Coneccion a la BD
    $select = Database::getConnection()->select('users_field_data', 'ud');
    $select->join('user__roles', 'ur', 'ud.uid = ur.entity_id');
    $select->fields('ud', array('name'));
    $select->condition('ur.roles_target_id','pentester');
    $results = $select->execute()->fetchCol();

    //Se crean las opciones para los revisores
    $form['revisores'] = array(
      '#title' => t('Asignar reviores:'),
      '#type' => 'checkboxes',
      '#options' => $results,
      '#required' => TRUE,
    );

    //Sitios
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $consulta = Database::getConnection()->select('sitios', 's');
    $consulta->fields('s', array('url_sitio'));
    $sitios = $consulta->execute()->fetchCol();
    Database::setActiveConnection();
    
    $form['sitios'] = array(
      '#title' => t('Sitios:'),
      '#type' => 'checkboxes',
      '#options' => $sitios,
      '#required' => TRUE,
    );

    //Boton para enviar el formulario
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Asignar revisión'),
    );
    $url = Url::fromUri('http://' . $_SERVER['SERVER_NAME'] . '/reportes/helloWorld.docx');
    $project_link = Link::fromTextAndUrl('Descargar', $url);
    $project_link = $project_link->toRenderable();
    $project_link['#attributes'] = array('class' => array('button'));
    $form['test'] = array('#markup' => render($project_link));
    return $form;
  }
  /*
   * (@inheritdoc)
   * Validacion de los datos ingresados
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //https://www.drupal.org/docs/drupal-apis/form-api/introduction-to-form-api
    //Conteo de sitios seleccionados
    $tmp = '';
    $test = $form_state->getValue(['sitios']);
    foreach($test as $valores){
      $tmp .= $valores.'-';
    }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    /*
    //Obtencion del valor de las opciones marcadas
    $tmp = 0;
    foreach($valores as $pos){
      $contenido[$tmp] = $form['sitios']['#options'][$pos];
      $tmp++;
    }*/
    //Validacion de tipo de revision
    $cadena = 'En revision de tipo Circular solo puedes escoger un sitio para revision.';
    if($form_state->getValue(['tipo']) == 1 && sizeof($valores) != 1){
      $form_state->setErrorByName('sitios',$cadena);
    }
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //Buscar nombres de los pentester asignados
    $tmp = '';
    $test = $form_state->getValue(['revisores']);
    foreach($test as $valores){
      $tmp .= $valores.'-';
    }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    $tmp = 0;
    foreach($valores as $pos){
      $nombresPentester[$tmp] = $form['revisores']['#options'][$pos];
      $tmp++;
    }
    //Buscar sus correos en la BD
    $consulta = Database::getConnection()->select('users_field_data', 'r');
    $consulta->fields('r', array('mail'));
    $consulta->condition('name',$nombresPentester, 'IN');
    $correos = $consulta->execute()->fetchCol();

    //Se manda correo
    $to = "";
    foreach($correos as $mail){
      $to .= $mail.',';
    }
    $to = substr($to, 0, -1);
    $mensaje = 'Revisión enviada.';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['context']['subject'] = "Asignación de revisión";
    $params['context']['message'] = 'Se le ha asignado una nueva revisión.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje = "Ocurrió algún error y no se ha podido enviar el correo de notificación.";}

    //id_revisiones
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $consulta = Database::getConnection()->select('revisiones', 'r');
    $consulta->addExpression('MAX(id_revision)','revisiones');
    $resultado = $consulta->execute()->fetchCol();
    $id_revisiones = $resultado[0] + 1;
    $consulta = Database::getConnection()->select('revisiones_sitios', 'r');
    $consulta->addExpression('MAX(id_rev_sitio)','revisiones_sitios');
    $resultado = $consulta->execute()->fetchCol();
    $id_rev_sitio = $resultado[0] + 1;
    Database::setActiveConnection();
    //uid_usuarios
    $consulta = Database::getConnection()->select('users_field_data', 'r');
    $consulta->fields('r', array('uid'));
    $consulta->condition('name',$nombresPentester, 'IN');
    $uid_usuarios = $consulta->execute()->fetchCol();
    //Obteniendo sitios seleccionados
    $tmp = '';
    $test = $form_state->getValue(['sitios']);
    foreach($test as $valores){
      $tmp .= $valores.'-';
    }
    $valores = explode('-',$tmp);
    while(end($valores) == 0 && sizeof($valores) > 1){$tmp = array_pop($valores);}
    $tmp = 0;
    foreach($valores as $pos){
      $url_sitios[$tmp] = $form['sitios']['#options'][$pos];
      $tmp++;
    }
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //id_sitios
    $consulta = Database::getConnection()->select('sitios', 'r');
    $consulta->fields('r', array('id_sitio'));
    $consulta->condition('url_sitio',$url_sitios, 'IN');
    $id_sitios = $consulta->execute()->fetchCol();
    Database::setActiveConnection();
    //Fecha
    $tmp = getdate();
    $fecha = $tmp['year'].'-'.$tmp['mon'].'-'.$tmp['mday'];
    //Otros datos
    $tipo_revision = $form_state->getValue(['tipo']);
    $estatus_revision = 1;
    //id_revisiones,uid_usuarios[],id_sitios[],$id_rev_sitio
    //Insercion en la BD
    //revisiones
    $result = $connection->insert('revisiones')
      ->fields(array(
        'id_revision' => $id_revisiones,
        'tipo_revision' => $tipo_revision,
        'id_estatus'=> $estatus_revision,
        'fecha_inicio_revision' => $fecha,
        'fecha_fin_revision' => NULL,
      ))->execute();
    //revisiones_asignadas
    foreach ($uid_usuarios as $pentester) {
      $result = $connection->insert('revisiones_asignadas')
        ->fields(array(
          'id_revision' => $id_revisiones,
          'id_usuario' => $pentester,
          'seguimiento' => 0,
        ))->execute();
    }
    $result = $connection->insert('revisiones_asignadas')
      ->fields(array(
        'id_revision' => $id_revisiones,
        'id_usuario' => \Drupal::currentUser()->id(),
        'seguimiento' => 0,
      ))->execute();
    //revisiones_sitios
    foreach ($id_sitios as $sitios) {
      $result = $connection->insert('revisiones_sitios')
        ->fields(array(
          'id_rev_sitio' => $id_rev_sitio,
          'id_revision' => $id_revisiones,
          'id_sitio' => $sitios,
        ))->execute();
      $id_rev_sitio++;
    }
    Database::setActiveConnection();
    /////////////////////////////////////////////////////////////////////////////////


    /*/Ejmplo plantilla
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantilla2.docx');
    if(1){
      $templateWord->cloneBlock('BORRAR2',0);
      $templateWord->setValue('header_name','mi header',1);
    }else{
      $templateWord->cloneBlock('BORRAR1',0);
    }///
    //$templateWord->setValue('header_name','mi header',1);
    $templateWord->setValue('footer_name','mi footer',1);
    //$templateWord->cloneBlock('CLONEME', 1);
    //$templateWord->setValue('DELETEME','borrado');
    //$templateWord->setValue('nombreReporte','nombre1',1);
    //$templateWord->setValue('columna1','valor columna ',1);
    $misDatos = [
        ['columna1' => 'prueba1', 'coluno' => 'miau', 'coldos' => 'miaux2'],
        ['columna1' => 'prueba2', 'coluno' => 'guau', 'coldos' => 'guaux2'],
    ];
    $templateWord->cloneRowAndSetValues('columna1', $misDatos);
    $templateWord->saveAs('reportes/helloWorld.docx');
    /*$templateWord->setValue('columna2','otro valor ',1);
    $templateWord->setValue('CANTIDAD','${TEST1}',1);
    $templateWord->setValue('CANTIDAD','${/TEST1}',1);
    $templateWord->cloneBlock('TEST1', 4);
    //$templateWord->setImageValue('datos','reportes/ldap.jpg',1);
    $templateWord->setValue('datos','sin array',1);
    $templateWord->setImageValue('datos',array('path' => 'reportes/ldap.jpg', 'width' => 100, 'height' => 100, 'ratio' => false),1);
    $templateWord->setValue('datos','con array',1);
    $templateWord->setImageValue('imagen',array('path' => 'reportes/ldap.jpg', 'width' => 100, 'height' => 100, 'ratio' => false),1);
    $templateWord->saveAs('reportes/helloWorld.docx');
    //*/




    //Rporte corto
    /*
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillaCorto.docx');
    ini_set("pcre.backtrack_limit", "-1");
    $rev_id = 1;
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentesters',$nombre,1);
    }
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();
    $templateWord->setValue('nombre_coordinador',$coordinador[0]);
    
    //Obtener url e ip sitio
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$no_rev);
    $sitioId = $select->execute()->fetchCol();
    //url
    $select = Database::getConnection()->select('sitios', 'r');
    $select->fields('r', array('url_sitio'));
    $select->condition('id_sitio',$sitioId,'IN');
    $urlSitio = $select->execute()->fetchCol();
    //ip
    $select = Database::getConnection()->select('ip_sitios', 'i');
    $select->join('dir_ip','d','i.id_ip = d.id_ip');
    $select->fields('d', array('dir_ip_sitios'));
    $select->condition('id_sitio',$sitioId,'IN');
    $ipSitio = $select->execute()->fetchCol();
    $tmp = getdate();
    $fechaY = $tmp['year'];

    $templateWord->setValue('sitio_web',$urlSitio[0]);
    $templateWord->setValue('dir_ip',$ipSitio[0]);
    $templateWord->setValue('fecha_fin_revision',$fecha);
    $templateWord->setValue('fecha_hoy',$fechaY);

    $c_critico = 0;
    $c_alto = 0;
    $c_medio = 0;
    $c_bajo = 0;
    $c_ni = 0;
    //hallazgos relacionados
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('revisiones_hallazgos','h','r.id_rev_sitio = h.id_rev_sitio');
    $select->fields('h', array('id_hallazgo'));
    $select->condition('id_revision',$rev_id);
    $idHallazgos = $select->execute();
    $numHallazgos = 0;
    foreach ($idHallazgos as $hallazgo) {$numHallazgos++;}
    $contadorHallazgo = 1;
    $contadorImg = 1;
    $templateWord->cloneBlock('HALLAZGO', $numHallazgos);
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->join('revisiones_hallazgos','h','r.id_rev_sitio = h.id_rev_sitio');
    $select->fields('h', array('id_rev_sitio_hall'));
    $select->fields('h', array('id_rev_sitio'));
    $select->fields('h', array('id_hallazgo'));
    $select->fields('h', array('impacto_hall_rev'));
    $select->fields('h', array('cvss_hallazgos'));
    $select->fields('h', array('recursos_afectador'));
    $select->fields('h', array('descripcion_hall_rev'));
    $select->condition('id_revision',$rev_id);
    $select->orderBy('impacto_hall_rev','DESC');
    $idHallazgos = $select->execute();
    foreach ($idHallazgos as $hallazgo) {
      //Identificación del nivel del hallazgo
      if($hallazgo->impacto_hall_rev >= 9.0){
        $c_critico++;
        $nivelH = 'Crítico - ' . $hallazgo->impacto_hall_rev;
      }elseif ($hallazgo->impacto_hall_rev >= 7.0) {
        $c_alto++;
        $nivelH = 'Alto - ' . $hallazgo->impacto_hall_rev;
      }elseif ($hallazgo->impacto_hall_rev >= 4.0) {
        $c_medio++;
        $nivelH = 'Medio - ' . $hallazgo->impacto_hall_rev;
      }elseif ($hallazgo->impacto_hall_rev >= 0.1) {
        $c_bajo++;
        $nivelH = 'Bajo - ' . $hallazgo->impacto_hall_rev;
      }else{
        $c_ni++;
        $nivelH = 'Sin impacto - ' . $hallazgo->impacto_hall_rev;
      }
      //Datos del hallazgo en el catálogo
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
      $select->fields('h',array('descripcion_hallazgo'));
      $select->fields('h',array('recomendacion_general_hallazgo'));
      $select->fields('h',array('nivel_cvss'));
      $select->fields('h',array('vector_cvss'));
      $select->fields('h',array('referencias_hallazgo'));
      $select->condition('id_hallazgo',$hallazgo->id_hallazgo);
      $datosHallazgos = $select->execute();
      foreach ($datosHallazgos as $dato) {
        $templateWord->setValue('NOMBRE_HALLAZGO',$contadorHallazgo . '. ' . $dato->nombre_hallazgo_vulnerabilidad,2);
        $templateWord->setValue('nivel_impacto',$nivelH,1);
        $templateWord->setValue('cvss_hallazgo',$hallazgo->cvss_hallazgos,1);
        $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
        $templateWord->setValue('recomendacionG_hallazgo',$dato->recomendacion_general_hallazgo,1);
        $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
      }
      $templateWord->setValue('descripcion_hallazgo_r',$hallazgo->descripcion_hall_rev,1);
      $templateWord->setValue('RECURSOS','${RECURSOS'.$hallazgo->id_hallazgo.'}',1);
      $templateWord->setValue('RECURSOS','${/RECURSOS'.$hallazgo->id_hallazgo.'}',1);
      $recursosAfectados = explode("\r", $hallazgo->recursos_afectador);
      $bloque = 'RECURSOS'.$hallazgo->id_hallazgo;
      $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
      foreach($recursosAfectados as $rec){
        $templateWord->setValue('recurso_afectado',$rec,1);
      }
      //Conteo de la cantidad de imágenes relacionadas a este hallazgo
      Database::setActiveConnection();
      $connection = \Drupal::service('database');
      //Cantidad de imágenes
      $select = $connection->select('file_managed', 'fm');
      $select->addExpression('COUNT(id_rev_sh)','file_managed');
      $select->condition('id_rev_sh', $hallazgo->id_rev_sitio_hall);
      $cantidadImagenesHallazgo = $select->execute()->fetchCol();

      $repImg = 'IMAGENES'.$contadorHallazgo;
      $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
      $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
      $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);

      //Imágenes y descripción
      $select = $connection->select('file_managed', 'fm')
        ->fields('fm', array('filename', 'descripcion'));
      $select->condition('id_rev_sh', $hallazgo->id_rev_sitio_hall);
      $imagenesHallazgo = $select->execute();
      foreach ($imagenesHallazgo as $imagenH) {
        $templateWord->setImageValue('imagen_revision',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
        $templateWord->setValue('contadorImg',$contadorImg,1);
        $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
        $contadorImg++;
      }
      
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      
      $contadorHallazgo++;
    }
    Database::setActiveConnection();
    //Conteo número de hallazgos por nivel
    $templateWord->setValue('c_critico',$c_critico);
    $templateWord->setValue('c_alto',$c_alto);
    $templateWord->setValue('c_medio',$c_medio);
    $templateWord->setValue('c_bajo',$c_bajo);
    $templateWord->setValue('c_ni',$c_ni);
    

    $templateWord->saveAs('reportes/helloWorld.docx');/*
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillaCorto.docx');

    $templateWord->saveAs('reportes/helloWorld.docx');
    //*/






    /*/Reporte completo un sitio
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$recID;
    }else{$mes = $fecha['mon'];}
    $nombreReporte = $fecha['year'] . $mes . '_';

    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillaCompleto1.docx');
    $rev_id = 2;
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();

    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentester',$nombre,1);
    }
    $templateWord->setValue('nombre_revisor',$coordinador[0]);
    $templateWord->setValue('nombre_visto_bueno',$coordinador[0]);
    
    //Obtener url e ip sitio
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$rev_id);
    $sitioId = $select->execute()->fetchCol();
    //url
    $select = Database::getConnection()->select('sitios', 'r');
    $select->fields('r', array('url_sitio'));
    $select->condition('id_sitio',$sitioId,'IN');
    $urlSitio = $select->execute()->fetchCol();
    //ip
    $select = Database::getConnection()->select('ip_sitios', 'i');
    $select->join('dir_ip','d','i.id_ip = d.id_ip');
    $select->fields('d', array('dir_ip_sitios'));
    $select->condition('id_sitio',$sitioId,'IN');
    $ipSitio = $select->execute()->fetchCol();
    //$tmp = getdate();
    //$fechaY = $tmp['year'];

    switch ($fecha['mon']) {
      case 1:
        $mes = 'Enero';
        break;
      case 2:
        $mes = 'Febrero';
        break;
      case 3:
        $mes = 'Marzo';
        break;
      case 4:
        $mes = 'Abril';
        break;
      case 5:
        $mes = 'Mayo';
        break;
      case 6:
        $mes = 'Junio';
        break;
      case 7:
        $mes = 'Julio';
        break;
      case 8:
        $mes = 'Agosto';
        break;
      case 9:
        $mes = 'Septiempbre';
        break;
      case 10:
        $mes = 'Octubre';
        break;
      case 11:
        $mes = 'Noviembre';
        break;
      case 12:
        $mes = 'Diciembre';
        break;
      default:
        $mes = 'XXXX';
        break;
    }
    $templateWord->setValue('DIA',$tmp['mday']);
    $templateWord->setValue('MES',$mes);
    $templateWord->setValue('ANIO',$tmp['year']);
    $templateWord->setValue('SITIO_WEB',$urlSitio[0]);
    $templateWord->setValue('DIR_IP',$ipSitio[0]);
    
    $nombreReporte .= $urlSitio[0].'_REV'.$rev_id.'_Oficio';

    $select = Database::getConnection()->select('revisiones_sitios', 's');
    $select->join('revisiones_hallazgos','h','s.id_rev_sitio = h.id_rev_sitio');
    $select->fields('h', array('id_rev_sitio_hall'));
    $select->condition('id_revision',$rev_id);
    $select->orderBy('impacto_hall_rev','DESC');
    $id_rev_sitio_hall = $select->execute()->fetchCol();
    $hallazgosV = array();
    $recomendacionesCortas = array();
    $recID = 1;
    $c_critico = 0;
    $c_alto = 0;
    $c_medio = 0;
    $c_bajo = 0;
    $c_ni = 0;
    $recomendacionesCortas = array();
    $anexoA = array();
    foreach ($id_rev_sitio_hall as $id) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->join('hallazgos','h', 'r.id_hallazgo = h.id_hallazgo');
      //$select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('descripcion_hall_rev'));
      $select->fields('r', array('recursos_afectador'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->fields('r', array('cvss_hallazgos'));
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $select->fields('h', array('descripcion_hallazgo'));
      $select->fields('h', array('solucion_recomendacion_halazgo'));
      $select->fields('h', array('referencias_hallazgo'));
      $select->fields('h', array('recomendacion_general_hallazgo'));
      $select->fields('h', array('nivel_cvss'));
      $select->fields('h', array('vector_cvss'));
      $select->fields('h', array('r_ejecutivo_hallazgo'));
      $select->fields('h', array('solucion_corta'));
      $select->condition('id_rev_sitio_hall',$id);
      $select->orderBy('impacto_hall_rev','DESC');
      $datos = $select->execute();
      foreach ($datos as $dato) {
        if($dato->impacto_hall_rev >= 9.0){
          $c_critico++;
          $nivelH = 'CRÍTICO';
        }elseif ($dato->impacto_hall_rev >= 7.0) {
          $c_alto++;
          $nivelH = 'ALTO';
        }elseif ($dato->impacto_hall_rev >= 4.0) {
          $c_medio++;
          $nivelH = 'MEDIO';
        }elseif ($dato->impacto_hall_rev >= 0.1) {
          $c_bajo++;
          $nivelH = 'BAJO';
        }else{
          $c_ni++;
          $nivelH = 'SIN IMPACTO';
        }
        if(strlen((string)$recID) == 1){
            $tmp = '0'.$recID;
        }
        $todo = array(
            'ACTIVO' => $urlSitio[0] . ' / ' . $ipSitio[0],
            'nombre_hallazgo' => $dato->nombre_hallazgo_vulnerabilidad,
            'rec_id' => $tmp,
            'nivel_impacto_n' => $dato->impacto_hall_rev,
            'nivel_impacto' => $nivelH,
        );
        array_push($hallazgosV,$todo);
        array_push($anexoA,array(
            'REC'.$tmp,
            $dato->nombre_hallazgo_vulnerabilidad,
            $dato->descripcion_hallazgo,
            $dato->solucion_recomendacion_halazgo,
            $dato->referencias_hallazgo,
            $dato->impacto_hall_rev,
            $nivelH,
            $dato->cvss_hallazgos
        ));
        if(!in_array($dato->solucion_corta, $recomendacionesCortas)){
          array_push($recomendacionesCortas,$dato->solucion_corta);
        }
      }
      $recID++;
    }
    //Conteo número de hallazgos por nivel
    $templateWord->setValue('c_critico',$c_critico);
    $templateWord->setValue('c_alto',$c_alto);
    $templateWord->setValue('c_medio',$c_medio);
    $templateWord->setValue('c_bajo',$c_bajo);
    $templateWord->setValue('c_ni',$c_ni);
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($recomendacionesCortas));
    foreach ($recomendacionesCortas as $recomendacion) {
      $templateWord->setValue('recomendación_general_h',$recomendacion,1);
    }
    //Se reemplaza en la tabla
    $templateWord->cloneRowAndSetValues('ACTIVO', $hallazgosV);
    
    //ANEXO A
    $templateWord->cloneBlock('TABLA_H',sizeof($anexoA));
    foreach ($anexoA as $tabla) {
      $templateWord->setValue('REC',$tabla[0],1);
      $templateWord->setValue('nombre_hallazgo',$tabla[1],1);
      $templateWord->setValue('descripcion_hallazgo',$tabla[2],1);
      $templateWord->setValue('recomendacionG_hallazgo',$tabla[3],1);
      $templateWord->setValue('url_hallazgo',$tabla[4],1);
      $templateWord->setValue('nivel_impacto_n',$tabla[5],1);
      $templateWord->setValue('nivel_impacto',$tabla[6],1);
      $templateWord->setValue('cvss_hallazgo',$tabla[7],1);
    }
    //ANEXO B
    $templateWord->cloneBlock('ANEXOB',sizeof($anexoA));
    $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
    $select->join('hallazgos','h', 'r.id_hallazgo = h.id_hallazgo');
    $select->fields('r', array('id_rev_sitio_hall'));
    $select->fields('r', array('id_hallazgo'));
    $select->fields('r', array('descripcion_hall_rev'));
    $select->fields('r', array('recursos_afectador'));
    $select->fields('r', array('impacto_hall_rev'));
    $select->fields('r', array('cvss_hallazgos'));
    $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
    $select->fields('h', array('descripcion_hallazgo'));
    $select->fields('h', array('solucion_recomendacion_halazgo'));
    $select->fields('h', array('referencias_hallazgo'));
    $select->fields('h', array('recomendacion_general_hallazgo'));
    $select->fields('h', array('nivel_cvss'));
    $select->fields('h', array('vector_cvss'));
    $select->fields('h', array('r_ejecutivo_hallazgo'));
    $select->orderBy('impacto_hall_rev','DESC');
    $select->condition('id_rev_sitio_hall',$id_rev_sitio_hall,'IN');
    $datos = $select->execute();
    Database::setActiveConnection();
    $connection = \Drupal::service('database');
    $contadorImg = 1;
    $contadorHallazgo = 1;
    foreach ($datos as $dato) {
      //ANEXO B
      if($dato->impacto_hall_rev >= 9.0){ $nivelH = 'CRÍTICO';
      }elseif ($dato->impacto_hall_rev >= 7.0) { $nivelH = 'ALTO';
      }elseif ($dato->impacto_hall_rev >= 4.0) { $nivelH = 'MEDIO';
      }elseif ($dato->impacto_hall_rev >= 0.1) { $nivelH = 'BAJO';
      }else{ $nivelH = 'SIN IMPACTO'; }
      $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
      $templateWord->setValue('nivel_impacto_n',$dato->impacto_hall_rev,1);
      $templateWord->setValue('nivel_impacto',$nivelH,1);
      $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
      $templateWord->setValue('descripcion_hall_rev',$dato->descripcion_hall_rev,1);
      $templateWord->setValue('RECURSOS','${RECURSOS'.$dato->id_hallazgo.'}',1);
      $templateWord->setValue('RECURSOS','${/RECURSOS'.$dato->id_hallazgo.'}',1);
      $recursosAfectados = explode("\r", $dato->recursos_afectador);
      $bloque = 'RECURSOS'.$dato->id_hallazgo;
      $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
      foreach($recursosAfectados as $rec){
        $templateWord->setValue('recurso_afectado',$rec,1);
      }

      //Cantidad de imágenes
      $select = $connection->select('file_managed', 'fm');
      $select->addExpression('COUNT(id_rev_sh)','file_managed');
      $select->condition('id_rev_sh', $dato->id_rev_sitio_hall);
      $cantidadImagenesHallazgo = $select->execute()->fetchCol();
      $repImg = 'IMAGENES'.$contadorHallazgo;
      $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
      $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
        
      if($cantidadImagenesHallazgo[0]){
        $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
        //Imágenes y descripción
        $select = $connection->select('file_managed', 'fm')
          ->fields('fm', array('filename', 'descripcion'));
        $select->condition('id_rev_sh', $dato->id_rev_sitio_hall);
        $imagenesHallazgo = $select->execute();
        foreach ($imagenesHallazgo as $imagenH) {
          $templateWord->setImageValue('imagen',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
          $templateWord->setValue('contadorImg',$contadorImg,1);
          $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
          $contadorImg++;
        }
      }/*else{
        $templateWord->deleteBlock($repImg);
      }///
      $contadorHallazgo++;
    }
    
    $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');
    //$templateWord->saveAs('reportes/helloWorld.docx');
    //*/






    /*Reporte completo varios sitios
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$fecha['mon'];
    }else{$mes = $fecha['mon'];}
    $nombreArchivo = $fecha['year'] . $mes . '_';
    
    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaCompleto2.docx');
    $rev_id = 2;
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();

    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentester',$nombre,1);
    }
    $templateWord->setValue('nombre_revisor',$coordinador[0]);
    $templateWord->setValue('nombre_visto_bueno',$coordinador[0]);
    
    switch ($fecha['mon']) {
      case 1:
        $mes = 'Enero';
        break;
      case 2:
        $mes = 'Febrero';
        break;
      case 3:
        $mes = 'Marzo';
        break;
      case 4:
        $mes = 'Abril';
        break;
      case 5:
        $mes = 'Mayo';
        break;
      case 6:
        $mes = 'Junio';
        break;
      case 7:
        $mes = 'Julio';
        break;
      case 8:
        $mes = 'Agosto';
        break;
      case 9:
        $mes = 'Septiempbre';
        break;
      case 10:
        $mes = 'Octubre';
        break;
      case 11:
        $mes = 'Noviembre';
        break;
      case 12:
        $mes = 'Diciembre';
        break;
      default:
        $mes = 'XXXX';
        break;
    }

    $templateWord->setValue('DIA',$fecha['mday']);
    $templateWord->setValue('MES',$mes);
    $templateWord->setValue('ANIO',$fecha['year']);
    
    $nombreArchivo .= 'variosSitios_REV'.$rev_id.'_Oficio';
    
    //Se ordenan los sitios y hallazgos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$rev_id);
    $id_rev_sitio = $select->execute()->fetchCol();
    $sitios = array();
    $hallazgos = array();
    $hall_ord = array();
    foreach ($id_rev_sitio as $sitio) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $impactos = $select->execute();
      $impacto = 0;
      foreach ($impactos as $valor) {
        if($valor > $impacto){
          $impacto = $valor->impacto_hall_rev;
        }
        if(!in_array($dato->id_hallazgo, $hallazgos)) {
          array_push($hallazgos, $valor->id_hallazgo);
        }
      }
      $sitios[$sitio] = $impacto;
    }
    arsort($sitios);//id_rev_sitio
    foreach ($hallazgos as $idhallazgo) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $select->orderBy('impacto_hall_rev','DESC');
      $impactos = $select->execute()->fetchCol();
      $valor = 0;
      foreach ($impactos as $impacto) {
        if($impacto > $valor){
          $valor = $impacto;
        }
      }
      $hall_ord[$idhallazgo] = $valor;
    }
    arsort($hall_ord);

    //Se organizan los sitios por id
    $datosSitios = array();
    $contadorSitios = 1;
    $alcance = array();
    $resumenEjecutivo = array();
    foreach ($sitios as $id_rs => $valor) {
      if(strlen((string)$contadorSitios) == 1){
        $sitID = '0'.$contadorSitios;
      }
      $select = Database::getConnection()->select('revisiones_sitios', 'r');
      $select->fields('r', array('id_sitio'));
      $select->condition('id_rev_sitio',$id_rs);
      $id_sitio = $select->execute()->fetchCol();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$id_sitio[0]);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$id_sitio[0]);
      $ipSitio = $select->execute()->fetchCol();
      array_push($datosSitios, array(
        'ACT' => $sitID,
        'id_s' => $id_sitio[0],
        'id_rs' => $id_rs,
      ));
      $c_critico = 0;
      $c_alto = 0;
      $c_medio = 0;
      $c_bajo = 0;
      $c_ni = 0;
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_critico++; }
        elseif ($valor >= 7.0) { $c_alto++; }
        elseif ($valor >= 4.0) { $c_medio++; }
        elseif ($valor >= 0.1) { $c_bajo++; }
        else{ $c_ni++; }
      }
      array_push($resumenEjecutivo,array(
        'id_activoR' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_critico' => $c_critico,
        'c_alto' => $c_alto,
        'c_medio' => $c_medio,
        'c_bajo' => $c_bajo,
        'c_ni' => $c_ni,
      ));
      array_push($alcance,array(
        'id_activoA' => $sitID,
        'SITIO_WEB' => $url[0],
        'dir_IP' => $ipSitio[0],
      ));
      $contadorSitios++;
    }
    //Tabla de alcance y resumen ejecutivo
    $templateWord->cloneRowAndSetValues('id_activoR', $resumenEjecutivo);
    $templateWord->cloneRowAndSetValues('id_activoA', $alcance);

    //Se organizan los hallazgos con id y valores
    $contadorHallazgo = 1;
    $datosHallazgos = array();
    foreach ($hall_ord as $idhallazgo => $valor) {
      if(strlen((string)$contadorHallazgo) == 1){
        $recID = '0'.$contadorHallazgo;
      }
      $valores = array();
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $select->orderBy('impacto_hall_rev','DESC');
      $impacto = $select->execute()->fetchCol();
      foreach ($impacto as $value) {
        if(!array_key_exists($value,$valores)){
          array_push($valores, $value);
        }
      }
      array_push($datosHallazgos, array(
        'ID' => 'REC'.$recID,
        'id_hall' => $idhallazgo,
        'valores' => $valores,
      ));
      $contadorHallazgo++;
    }

    //Estado actual de seguridad
    $estadoActual = array();
    foreach ($datosSitios as $sitio) {
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      //id_hallazgos
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $id_h = $select->execute()->fetchCol();
      foreach ($datosHallazgos as $hallazgo) {
        if(in_array($hallazgo['id_hall'], $id_h)){
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
          $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
          $select->fields('r', array('impacto_hall_rev'));
          $select->condition('id_rev_sitio',$sitio['id_rs']);
          $select->condition('r.id_hallazgo',$hallazgo['id_hall']);
          $informacion = $select->execute();
          foreach ($informacion as $info) {
            if($info->impacto_hall_rev >= 9.0){
              $nivelH = 'CRÍTICO';
            }elseif ($info->impacto_hall_rev >= 7.0) {
              $nivelH = 'ALTO';
            }elseif ($info->impacto_hall_rev >= 4.0) {
              $nivelH = 'MEDIO';
            }elseif ($info->impacto_hall_rev >= 0.1) {
              $nivelH = 'BAJO';
            }else{
              $nivelH = 'SIN IMPACTO';
            }
            array_push($estadoActual,array(
              'id_activoE' => $sitio['ACT'],
              'ACTIVO' => $url[0] . '/' . $ipSitio[0],
              'nombre_hallazgo' => $info->nombre_hallazgo_vulnerabilidad,
              'rec_id' => $hallazgo['ID'],
              'nivel_impacto_n' => $info->impacto_hall_rev,
              'nivel_impacto' => $nivelH,
            ));
          }
        }
      }      
    }
    $templateWord->cloneRowAndSetValues('id_activoE', $estadoActual);

    $solucion_corta = array();
    //Anexo A
    $templateWord->cloneBlock('TABLA_H',sizeof($datosHallazgos));
    foreach ($datosHallazgos as $hallazgo) {
      $templateWord->setValue('REC',$hallazgo['ID'],1);
      $select = Database::getConnection()->select('hallazgos', 'h');
      $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
      $select->fields('h', array('descripcion_hallazgo'));
      $select->fields('h', array('solucion_recomendacion_halazgo'));
      $select->fields('h', array('referencias_hallazgo'));
      $select->fields('h', array('solucion_corta'));
      $select->condition('h.id_hallazgo',$hallazgo['id_hall']);
      $datos = $select->execute();
      foreach ($datos as $dato) {
        $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
        $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
        $templateWord->setValue('recomendacionG_hallazgo',$dato->solucion_recomendacion_halazgo,1);
        $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
        if(!in_array($dato->solucion_corta, $solucion_corta)){array_push($solucion_corta, $dato->solucion_corta);}
      }
      $iteracion = 'DETALLES_HM';
      $templateWord->setValue('DETALLES_H','${'.$iteracion.'}',1);
      $templateWord->setValue('DETALLES_H','${/'.$iteracion.'}',1);
      $templateWord->cloneBlock($iteracion,sizeof($hallazgo['valores']));
      rsort($hallazgo['valores']);
      foreach ($hallazgo['valores'] as $impacto) {
        if($impacto >= 9.0){
          $nivelH = 'CRÍTICO';
        }elseif ($impacto >= 7.0) {
          $nivelH = 'ALTO';
        }elseif ($impacto >= 4.0) {
          $nivelH = 'MEDIO';
        }elseif ($impacto >= 0.1) {
          $nivelH = 'BAJO';
        }else{
          $nivelH = 'SIN IMPACTO';
        }
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->fields('r', array('cvss_hallazgos'));
        $select->condition('impacto_hall_rev',(string)$impacto);
        $select->condition('id_rev_sitio',array_keys($sitios),'IN');
        $cvss = $select->execute()->fetchCol();
        $templateWord->setValue('nivel_impacto_n',$impacto,1);
        $templateWord->setValue('nivel_impacto',$nivelH,1);
        $templateWord->setValue('cvss_hallazgo',$cvss[0],1);
      }
    }
    //Recomendaciones generales
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($solucion_corta));
    foreach ($solucion_corta as $solucion) {
      $templateWord->setValue('recomendación_general_h',$solucion,1);
    }
    Database::setActiveConnection();

    //Anexo B
    $contadorImg = 1;
    $templateWord->cloneBlock('ANEXOB',sizeof($datosSitios));
    foreach ($datosSitios as $sitio) {
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      $templateWord->setValue('idSitio',$sitio['ACT'],1);
      $templateWord->setValue('SITIO_WEB',$url[0],1);
      $templateWord->setValue('DIR_IP',$ipSitio[0],1);
      //Se busca la cantidad de hallazgos por sitio
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $select->orderBy('impacto_hall_rev','DESC');
      $id_hall = $select->execute()->fetchCol();
      $cantidad = 'HALLAZGOBA';
      $templateWord->setValue('HALLAZGOB','${'.$cantidad.'}',1);
      $templateWord->setValue('HALLAZGOB','${/'.$cantidad.'}',1);
      $templateWord->cloneBlock($cantidad,sizeof($id_hall));
      foreach ($id_hall as $id) {
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
        $select->fields('r',array('id_rev_sitio_hall'));
        $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('r',array('impacto_hall_rev'));
        $select->fields('h',array('descripcion_hallazgo'));
        $select->fields('r',array('descripcion_hall_rev'));
        $select->fields('r',array('recursos_afectador'));
        $select->condition('r.id_hallazgo',(string)$id);
        $select->condition('id_rev_sitio',$sitio['id_rs']);
        $results = $select->execute();
        Database::setActiveConnection();
        $connection = \Drupal::service('database');
        foreach ($results as $result) {
          if($result->impacto_hall_rev >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($result->impacto_hall_rev >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($result->impacto_hall_rev >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($result->impacto_hall_rev >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $templateWord->setValue('nombre_hallazgo',$result->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('nivel_impacto_n',$result->impacto_hall_rev,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('descripcion_hallazgo',$result->descripcion_hallazgo,1);
          $templateWord->setValue('descripcion_hall_rev',$result->descripcion_hall_rev,1);
          $recursosAfectados = explode("\r", $result->recursos_afectador);
          $bloque = 'RECURSOS1';
          $templateWord->setValue('RECURSOS','${'.$bloque.'}',1);
          $templateWord->setValue('RECURSOS','${/'.$bloque.'}',1);
          $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
          foreach($recursosAfectados as $rec){
            $templateWord->setValue('recurso_afectado',$rec,1);
          }
          //Cantidad de imágenes
          $select = $connection->select('file_managed', 'fm');
          $select->addExpression('COUNT(id_rev_sh)','file_managed');
          $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
          $cantidadImagenesHallazgo = $select->execute()->fetchCol();
          $repImg = 'IMAGENESH';
          $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
          $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
            
          if($cantidadImagenesHallazgo[0]){
            $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
            //Imágenes y descripción
            $select = $connection->select('file_managed', 'fm')
              ->fields('fm', array('filename', 'descripcion'));
            $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
            $imagenesHallazgo = $select->execute();
            foreach ($imagenesHallazgo as $imagenH) {
              $templateWord->setImageValue('imagen',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
              $templateWord->setValue('contadorImg',$contadorImg,1);
              $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
              $contadorImg++;
            }
          }/*else{
            $templateWord->deleteBlock($repImg);
          }///
        }
      }
    }

    $templateWord->saveAs('reportes/helloWorld.docx');
    //$templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');

    //*/
    
    /*
    https://sandritascs.blogspot.com/2015/03/crear-documentos-word-doc-docx-con.html
    https://parzibyte.me/blog/2019/06/13/crear-documentos-word-php-phpword/
    */






    /*/Reporte seguimiento un sitio
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$fecha['mon'];
    }else{$mes = $fecha['mon'];}
    $nombreReporte = $fecha['year'] . $mes . '_';

    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaSeguimiento1.docx');
    $no_rev = 1;
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$no_rev);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();

    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentester',$nombre,1);
    }
    $templateWord->setValue('nombre_revisor',$coordinador[0]);
    $templateWord->setValue('nombre_visto_bueno',$coordinador[0]);
    
    //Obtener url e ip sitio
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$no_rev);
    $sitioId = $select->execute()->fetchCol();
    //url
    $select = Database::getConnection()->select('sitios', 'r');
    $select->fields('r', array('url_sitio'));
    $select->condition('id_sitio',$sitioId,'IN');
    $urlSitio = $select->execute()->fetchCol();
    //ip
    $select = Database::getConnection()->select('ip_sitios', 'i');
    $select->join('dir_ip','d','i.id_ip = d.id_ip');
    $select->fields('d', array('dir_ip_sitios'));
    $select->condition('id_sitio',$sitioId,'IN');
    $ipSitio = $select->execute()->fetchCol();
    //$tmp = getdate();
    //$fechaY = $tmp['year'];

    switch ($fecha['mon']) {
      case 1:
        $mes = 'Enero';
        break;
      case 2:
        $mes = 'Febrero';
        break;
      case 3:
        $mes = 'Marzo';
        break;
      case 4:
        $mes = 'Abril';
        break;
      case 5:
        $mes = 'Mayo';
        break;
      case 6:
        $mes = 'Junio';
        break;
      case 7:
        $mes = 'Julio';
        break;
      case 8:
        $mes = 'Agosto';
        break;
      case 9:
        $mes = 'Septiempbre';
        break;
      case 10:
        $mes = 'Octubre';
        break;
      case 11:
        $mes = 'Noviembre';
        break;
      case 12:
        $mes = 'Diciembre';
        break;
      default:
        $mes = 'XXXX';
        break;
    }
    $templateWord->setValue('DIA',$tmp['mday']);
    $templateWord->setValue('MES',$mes);
    $templateWord->setValue('ANIO',$tmp['year']);
    $templateWord->setValue('URL_SITIO',$urlSitio[0]);
    $templateWord->setValue('DIR_IP',$ipSitio[0]);
    
    $nombreReporte .= $urlSitio[0].'_REV'.$no_rev.'_Oficio_Seguimiento';

    //Se ordenan los sitios y hallazgos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$no_rev);
    $id_rev_sitio = $select->execute()->fetchCol();
    $sitios = array();
    $hallazgos = array();
    $hall_ord = array();
    foreach ($id_rev_sitio as $sitio) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $impactos = $select->execute();
      $impacto = 0;
      foreach ($impactos as $valor) {
        if($valor > $impacto){
          $impacto = $valor->impacto_hall_rev;
        }
        if(!in_array($dato->id_hallazgo, $hallazgos)) {
          array_push($hallazgos, $valor->id_hallazgo);
        }
      }
      $sitios[$sitio] = $impacto;
    }
    foreach ($hallazgos as $idhallazgo) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $select->condition('id_hallazgo',$idhallazgo);
      $impacto = $select->execute()->fetchCol();
      if(!array_key_exists($id_hallazgo,$hall_ord)){
        $hall_ord[$idhallazgo] = $impacto;
      }elseif ($impacto > $hall_ord[$idhallazgo]) {
        $hall_ord[$idhallazgo] = $impacto;
      }
    }
    arsort($sitios);//id_rev_sitio
    arsort($hall_ord);

    //Se organizan los sitios por id
    $datosSitios = array();
    $contadorSitios = 1;
    $alcance = array();
    $resumenEjecutivo1 = array();
    $resumenEjecutivo2 = array();
    foreach ($sitios as $id_rs => $valor) {
      if(strlen((string)$contadorSitios) == 1){
        $sitID = '0'.$contadorSitios;
      }
      $select = Database::getConnection()->select('revisiones_sitios', 'r');
      $select->fields('r', array('id_sitio'));
      $select->condition('id_rev_sitio',$id_rs);
      $id_sitio = $select->execute()->fetchCol();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$id_sitio[0]);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$id_sitio[0]);
      $ipSitio = $select->execute()->fetchCol();
      array_push($datosSitios, array(
        'ACT' => $sitID,
        'id_s' => $id_sitio[0],
        'id_rs' => $id_rs,
      ));
      $c_critico = 0;
      $c_alto = 0;
      $c_medio = 0;
      $c_bajo = 0;
      $c_ni = 0;
      $c_criticoP = 0;
      $c_altoP = 0;
      $c_medioP = 0;
      $c_bajoP = 0;
      $c_niP = 0;
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $select->condition('estatus',true);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_criticoP++; }
        elseif ($valor >= 7.0) { $c_altoP++; }
        elseif ($valor >= 4.0) { $c_medioP++; }
        elseif ($valor >= 0.1) { $c_bajoP++; }
        else{ $c_ni++; }
      }
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $select->condition('estatus',false);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_critico++; }
        elseif ($valor >= 7.0) { $c_alto++; }
        elseif ($valor >= 4.0) { $c_medio++; }
        elseif ($valor >= 0.1) { $c_bajo++; }
        else{ $c_ni++; }
      }
      array_push($resumenEjecutivo1,array(
        'id_activoR1' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_criticoP' => $c_criticoP,
        'c_altoP' => $c_altoP,
        'c_medioP' => $c_medioP,
        'c_bajoP' => $c_bajoP,
        'c_niP' => $c_niP,
      ));
      array_push($resumenEjecutivo2,array(
        'id_activoR2' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_critico' => $c_critico,
        'c_alto' => $c_alto,
        'c_medio' => $c_medio,
        'c_bajo' => $c_bajo,
        'c_ni' => $c_ni,
      ));
      array_push($alcance,array(
        'id_activoA' => $sitID,
        'SITIO_WEB' => $url[0],
        'dir_IP' => $ipSitio[0],
      ));
      $contadorSitios++;
    }
    //Tabla de alcance y resumen ejecutivo
    $templateWord->cloneRowAndSetValues('id_activoR1', $resumenEjecutivo1);
    $templateWord->cloneRowAndSetValues('id_activoR2', $resumenEjecutivo2);
    $templateWord->cloneRowAndSetValues('id_activoA', $alcance);

    //Se organizan los hallazgos con id y valores
    $contadorHallazgo = 1;
    $datosHallazgos = array();
    foreach ($hall_ord as $idhallazgo => $valor) {
      if(strlen((string)$contadorHallazgo) == 1){
        $recID = '0'.$contadorHallazgo;
      }
      $valores = array();
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $impacto = $select->execute()->fetchCol();
      foreach ($impacto as $value) {
        if(!array_key_exists($value,$valores)){
          array_push($valores, $value);
        }
      }
      array_push($datosHallazgos, array(
        'ID' => 'REC'.$recID,
        'id_hall' => $idhallazgo,
        'valores' => $valores,
      ));
      $contadorHallazgo++;
    }

    //Estado actual de seguridad
    $estadoActual = array();
    $anexoAtimes = 0;
    foreach ($datosSitios as $sitio) {
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      //id_hallazgos
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $id_h = $select->execute()->fetchCol();
      foreach ($datosHallazgos as $hallazgo) {
        if(in_array($hallazgo['id_hall'], $id_h)){
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
          $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
          $select->fields('r', array('impacto_hall_rev'));
          $select->fields('r', array('estatus'));
          $select->condition('id_rev_sitio',$sitio['id_rs']);
          $select->condition('r.id_hallazgo',$hallazgo['id_hall']);
          $informacion = $select->execute();
          foreach ($informacion as $info) {
            if($info->impacto_hall_rev >= 9.0){
              $nivelH = 'CRÍTICO';
            }elseif ($info->impacto_hall_rev >= 7.0) {
              $nivelH = 'ALTO';
            }elseif ($info->impacto_hall_rev >= 4.0) {
              $nivelH = 'MEDIO';
            }elseif ($info->impacto_hall_rev >= 0.1) {
              $nivelH = 'BAJO';
            }else{
              $nivelH = 'SIN IMPACTO';
            }
            if($info->estatus){
              array_push($estadoActual,array(
                'id_activoE' => $sitio['ACT'],
                'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
                'nombre_hallazgo' => $info->nombre_hallazgo_vulnerabilidad,
                'rec_id' => $hallazgo['ID'],
                'nivel_impacto_n' => $info->impacto_hall_rev,
                'nivel_impacto' => $nivelH,
                'estatus' => 'PERSISTENTE',
              ));
              $anexoAtimes++;
            }else{
              array_push($estadoActual,array(
                'id_activoE' => $sitio['ACT'],
                'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
                'nombre_hallazgo' => '-',
                'rec_id' => '-',
                'nivel_impacto_n' => '-',
                'nivel_impacto' => '-',
                'estatus' => 'MITIGADO',
              ));
            }
          }
        }
      }      
    }
    $templateWord->cloneRowAndSetValues('id_activoE', $estadoActual);

    $solucion_corta = array();
    //Anexo A
    $templateWord->cloneBlock('TABLA_H',$anexoAtimes);
    foreach ($datosHallazgos as $hallazgo) {
      //Se revisa el estatus
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->join('revisiones_sitios','r','r.id_rev_sitio = h.id_rev_sitio');
      $select->fields('h', array('estatus'));
      $select->condition('id_hallazgo',$hallazgo['id_hall']);
      $select->condition('id_revision',$no_rev);
      $estado = $select->execute()->fetchCol();
      //Si es persistente, se realiza
      if($estado[0]){
        $templateWord->setValue('REC',$hallazgo['ID'],1);
        $select = Database::getConnection()->select('hallazgos', 'h');
        $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('h', array('descripcion_hallazgo'));
        $select->fields('h', array('solucion_recomendacion_halazgo'));
        $select->fields('h', array('referencias_hallazgo'));
        $select->fields('h', array('solucion_corta'));
        $select->condition('h.id_hallazgo',$hallazgo['id_hall']);
        $datos = $select->execute();
        foreach ($datos as $dato) {
          $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
          $templateWord->setValue('recomendacionG_hallazgo',$dato->solucion_recomendacion_halazgo,1);
          $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
          if(!in_array($dato->solucion_corta, $solucion_corta)){array_push($solucion_corta, $dato->solucion_corta);}
        }
        $iteracion = 'DETALLES_HM';
        $templateWord->setValue('DETALLES_H','${'.$iteracion.'}',1);
        $templateWord->setValue('DETALLES_H','${/'.$iteracion.'}',1);
        $templateWord->cloneBlock($iteracion,sizeof($hallazgo['valores']));
        rsort($hallazgo['valores']);
        foreach ($hallazgo['valores'] as $impacto) {
          if($impacto >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($impacto >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($impacto >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($impacto >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->fields('r', array('cvss_hallazgos'));
          $select->condition('impacto_hall_rev',(string)$impacto);
          $select->condition('id_rev_sitio',array_keys($sitios),'IN');
          $cvss = $select->execute()->fetchCol();
          $templateWord->setValue('nivel_impacto_n',$impacto,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('cvss_hallazgo',$cvss[0],1);
        }
      }
    }
    //Recomendaciones generales
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($solucion_corta));
    foreach ($solucion_corta as $solucion) {
      $templateWord->setValue('recomendación_general_h',$solucion,1);
    }
    Database::setActiveConnection();

    //Anexo B
    $contadorImg = 1;
    $templateWord->cloneBlock('ANEXOB',sizeof($datosSitios));
    foreach ($datosSitios as $sitio) {
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      $templateWord->setValue('idSitio',$sitio['ACT'],1);
      $templateWord->setValue('SITIO_WEB',$url[0],1);
      $templateWord->setValue('DIR_IP',$ipSitio[0],1);
      //Se busca la cantidad de hallazgos por sitio
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $select->condition('estatus',true);
      $select->orderBy('impacto_hall_rev','DESC');
      $id_hall = $select->execute()->fetchCol();
      $cantidad = 'HALLAZGOBA';
      $templateWord->setValue('HALLAZGOB','${'.$cantidad.'}',1);
      $templateWord->setValue('HALLAZGOB','${/'.$cantidad.'}',1);
      $templateWord->cloneBlock($cantidad,sizeof($id_hall));
      foreach ($id_hall as $id) {
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
        $select->fields('r',array('id_rev_sitio_hall'));
        $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('r',array('impacto_hall_rev'));
        $select->fields('h',array('descripcion_hallazgo'));
        $select->fields('r',array('descripcion_hall_rev'));
        $select->fields('r',array('recursos_afectador'));
        $select->condition('r.id_hallazgo',(string)$id);
        $select->condition('id_rev_sitio',$sitio['id_rs']);
        $results = $select->execute();
        Database::setActiveConnection();
        $connection = \Drupal::service('database');
        foreach ($results as $result) {
          if($result->impacto_hall_rev >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($result->impacto_hall_rev >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($result->impacto_hall_rev >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($result->impacto_hall_rev >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $templateWord->setValue('nombre_hallazgo',$result->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('nivel_impacto_n',$result->impacto_hall_rev,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('descripcion_hallazgo',$result->descripcion_hallazgo,1);
          $templateWord->setValue('descripcion_hall_rev',$result->descripcion_hall_rev,1);
          $recursosAfectados = explode("\r", $result->recursos_afectador);
          $bloque = 'RECURSOS1';
          $templateWord->setValue('RECURSOS','${'.$bloque.'}',1);
          $templateWord->setValue('RECURSOS','${/'.$bloque.'}',1);
          $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
          foreach($recursosAfectados as $rec){
            $templateWord->setValue('recurso_afectado',$rec,1);
          }
          //Cantidad de imágenes
          $select = $connection->select('file_managed', 'fm');
          $select->addExpression('COUNT(id_rev_sh)','file_managed');
          $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
          $cantidadImagenesHallazgo = $select->execute()->fetchCol();
          $repImg = 'IMAGENESH';
          $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
          $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
            
          if($cantidadImagenesHallazgo[0]){
            $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
            //Imágenes y descripción
            $select = $connection->select('file_managed', 'fm')
              ->fields('fm', array('filename', 'descripcion'));
            $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
            $imagenesHallazgo = $select->execute();
            foreach ($imagenesHallazgo as $imagenH) {
              $templateWord->setImageValue('imagen',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
              $templateWord->setValue('contadorImg',$contadorImg,1);
              $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
              $contadorImg++;
            }
          }else{
            $templateWord->cloneBlock($repImg,0);
          }///
        }
      }
    }

    $templateWord->saveAs('reportes/helloWorld.docx');
    //*/







    /*/Reporte seguimiento varios sitios
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$fecha['mon'];
    }else{$mes = $fecha['mon'];}
    $nombreReporte = $fecha['year'] . $mes . '_';

    $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaSeguimiento2.docx');
    $no_rev = 2;
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$no_rev);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();

    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentester',$nombre,1);
    }
    $templateWord->setValue('nombre_revisor',$coordinador[0]);
    $templateWord->setValue('nombre_visto_bueno',$coordinador[0]);
    
    //Obtener url e ip sitio
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$no_rev);
    $sitioId = $select->execute()->fetchCol();
    //url
    $select = Database::getConnection()->select('sitios', 'r');
    $select->fields('r', array('url_sitio'));
    $select->condition('id_sitio',$sitioId,'IN');
    $urlSitio = $select->execute()->fetchCol();
    //ip
    $select = Database::getConnection()->select('ip_sitios', 'i');
    $select->join('dir_ip','d','i.id_ip = d.id_ip');
    $select->fields('d', array('dir_ip_sitios'));
    $select->condition('id_sitio',$sitioId,'IN');
    $ipSitio = $select->execute()->fetchCol();*
    //$tmp = getdate();
    //$fechaY = $tmp['year'];

    switch ($fecha['mon']) {
      case 1:
        $mes = 'Enero';
        break;
      case 2:
        $mes = 'Febrero';
        break;
      case 3:
        $mes = 'Marzo';
        break;
      case 4:
        $mes = 'Abril';
        break;
      case 5:
        $mes = 'Mayo';
        break;
      case 6:
        $mes = 'Junio';
        break;
      case 7:
        $mes = 'Julio';
        break;
      case 8:
        $mes = 'Agosto';
        break;
      case 9:
        $mes = 'Septiempbre';
        break;
      case 10:
        $mes = 'Octubre';
        break;
      case 11:
        $mes = 'Noviembre';
        break;
      case 12:
        $mes = 'Diciembre';
        break;
      default:
        $mes = 'XXXX';
        break;
    }
    $templateWord->setValue('DIA',$tmp['mday']);
    $templateWord->setValue('MES',$mes);
    $templateWord->setValue('ANIO',$tmp['year']);
    //$templateWord->setValue('URL_SITIO',$urlSitio[0]);
    //$templateWord->setValue('DIR_IP',$ipSitio[0]);
    
    $nombreReporte .= $urlSitio[0].'_REV'.$no_rev.'_Oficio_Seguimiento';

    //Se ordenan los sitios y hallazgos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$no_rev);
    $id_rev_sitio = $select->execute()->fetchCol();
    $sitios = array();
    $hallazgos = array();
    $hall_ord = array();
    foreach ($id_rev_sitio as $sitio) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $impactos = $select->execute();
      $impacto = 0;
      foreach ($impactos as $valor) {
        if($valor > $impacto){
          $impacto = $valor->impacto_hall_rev;
        }
        if(!in_array($dato->id_hallazgo, $hallazgos)) {
          array_push($hallazgos, $valor->id_hallazgo);
        }
      }
      $sitios[$sitio] = $impacto;
    }
    arsort($sitios);//id_rev_sitio
    foreach ($hallazgos as $idhallazgo) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $select->orderBy('impacto_hall_rev','DESC');
      $impactos = $select->execute()->fetchCol();
      $valor = 0;
      foreach ($impactos as $impacto) {
        if($impacto > $valor){
          $valor = $impacto;
        }
      }
      $hall_ord[$idhallazgo] = $valor;
    }
    arsort($hall_ord);

    //Se organizan los sitios por id
    $datosSitios = array();
    $contadorSitios = 1;
    $alcance = array();
    $resumenEjecutivo1 = array();
    $resumenEjecutivo2 = array();
    foreach ($sitios as $id_rs => $valor) {
      if(strlen((string)$contadorSitios) == 1){
        $sitID = '0'.$contadorSitios;
      }
      $select = Database::getConnection()->select('revisiones_sitios', 'r');
      $select->fields('r', array('id_sitio'));
      $select->condition('id_rev_sitio',$id_rs);
      $id_sitio = $select->execute()->fetchCol();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$id_sitio[0]);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$id_sitio[0]);
      $ipSitio = $select->execute()->fetchCol();
      array_push($datosSitios, array(
        'ACT' => $sitID,
        'id_s' => $id_sitio[0],
        'id_rs' => $id_rs,
      ));
      $c_critico = 0;
      $c_alto = 0;
      $c_medio = 0;
      $c_bajo = 0;
      $c_ni = 0;
      $c_criticoP = 0;
      $c_altoP = 0;
      $c_medioP = 0;
      $c_bajoP = 0;
      $c_niP = 0;
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $select->condition('estatus',true);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_criticoP++; }
        elseif ($valor >= 7.0) { $c_altoP++; }
        elseif ($valor >= 4.0) { $c_medioP++; }
        elseif ($valor >= 0.1) { $c_bajoP++; }
        else{ $c_ni++; }
      }
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $select->condition('estatus',false);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_critico++; }
        elseif ($valor >= 7.0) { $c_alto++; }
        elseif ($valor >= 4.0) { $c_medio++; }
        elseif ($valor >= 0.1) { $c_bajo++; }
        else{ $c_ni++; }
      }
      array_push($resumenEjecutivo1,array(
        'id_activoR1' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_criticoP' => $c_criticoP,
        'c_altoP' => $c_altoP,
        'c_medioP' => $c_medioP,
        'c_bajoP' => $c_bajoP,
        'c_niP' => $c_niP,
      ));
      array_push($resumenEjecutivo2,array(
        'id_activoR2' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_critico' => $c_critico,
        'c_alto' => $c_alto,
        'c_medio' => $c_medio,
        'c_bajo' => $c_bajo,
        'c_ni' => $c_ni,
      ));
      array_push($alcance,array(
        'id_activoA' => $sitID,
        'SITIO_WEB' => $url[0],
        'dir_IP' => $ipSitio[0],
      ));
      $contadorSitios++;
    }
    //Tabla de alcance y resumen ejecutivo
    $templateWord->cloneRowAndSetValues('id_activoR1', $resumenEjecutivo1);
    $templateWord->cloneRowAndSetValues('id_activoR2', $resumenEjecutivo2);
    $templateWord->cloneRowAndSetValues('id_activoA', $alcance);

    //Se organizan los hallazgos con id y valores
    $contadorHallazgo = 1;
    $datosHallazgos = array();
    foreach ($hall_ord as $idhallazgo => $valor) {
      if(strlen((string)$contadorHallazgo) == 1){
        $recID = '0'.$contadorHallazgo;
      }
      $valores = array();
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $select->orderBy('impacto_hall_rev','DESC');
      $impacto = $select->execute()->fetchCol();
      foreach ($impacto as $value) {
        if(!array_key_exists($value,$valores)){
          array_push($valores, $value);
        }
      }
      array_push($datosHallazgos, array(
        'ID' => 'REC'.$recID,
        'id_hall' => $idhallazgo,
        'valores' => $valores,
      ));
      $contadorHallazgo++;
    }

    //Estado actual de seguridad
    $estadoActual = array();
    $anexoAtimes = 0;
    foreach ($datosSitios as $sitio) {
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      //id_hallazgos
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $id_h = $select->execute()->fetchCol();
      foreach ($datosHallazgos as $hallazgo) {
        if(in_array($hallazgo['id_hall'], $id_h)){
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
          $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
          $select->fields('r', array('impacto_hall_rev'));
          $select->fields('r', array('estatus'));
          $select->condition('id_rev_sitio',$sitio['id_rs']);
          $select->condition('r.id_hallazgo',$hallazgo['id_hall']);
          $informacion = $select->execute();
          foreach ($informacion as $info) {
            if($info->impacto_hall_rev >= 9.0){
              $nivelH = 'CRÍTICO';
            }elseif ($info->impacto_hall_rev >= 7.0) {
              $nivelH = 'ALTO';
            }elseif ($info->impacto_hall_rev >= 4.0) {
              $nivelH = 'MEDIO';
            }elseif ($info->impacto_hall_rev >= 0.1) {
              $nivelH = 'BAJO';
            }else{
              $nivelH = 'SIN IMPACTO';
            }
            if($info->estatus){
              array_push($estadoActual,array(
                'id_activoE' => $sitio['ACT'],
                'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
                'nombre_hallazgo' => $info->nombre_hallazgo_vulnerabilidad,
                'rec_id' => $hallazgo['ID'],
                'nivel_impacto_n' => $info->impacto_hall_rev,
                'nivel_impacto' => $nivelH,
                'estatus' => 'PERSISTENTE',
              ));
              $anexoAtimes++;
            }else{
              array_push($estadoActual,array(
                'id_activoE' => $sitio['ACT'],
                'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
                'nombre_hallazgo' => '-',
                'rec_id' => '-',
                'nivel_impacto_n' => '-',
                'nivel_impacto' => '-',
                'estatus' => 'MITIGADO',
              ));
            }
          }
        }
      }      
    }
    $templateWord->cloneRowAndSetValues('id_activoE', $estadoActual);

    $solucion_corta = array();
    //Anexo A
    $templateWord->cloneBlock('TABLA_H',sizeof(array_keys($hall_ord)));
    foreach ($datosHallazgos as $hallazgo) {
      //Se revisa el estatus
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->join('revisiones_sitios','r','r.id_rev_sitio = h.id_rev_sitio');
      $select->fields('h', array('estatus'));
      $select->condition('id_hallazgo',$hallazgo['id_hall']);
      $select->condition('id_revision',$no_rev);
      $estado = $select->execute()->fetchCol();
      //Si es persistente, se realiza
      if($estado[0]){
        $templateWord->setValue('REC',$hallazgo['ID'],1);
        $select = Database::getConnection()->select('hallazgos', 'h');
        $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('h', array('descripcion_hallazgo'));
        $select->fields('h', array('solucion_recomendacion_halazgo'));
        $select->fields('h', array('referencias_hallazgo'));
        $select->fields('h', array('solucion_corta'));
        $select->condition('h.id_hallazgo',$hallazgo['id_hall']);
        $datos = $select->execute();
        foreach ($datos as $dato) {
          $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
          $templateWord->setValue('recomendacionG_hallazgo',$dato->solucion_recomendacion_halazgo,1);
          $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
          if(!in_array($dato->solucion_corta, $solucion_corta)){array_push($solucion_corta, $dato->solucion_corta);}
        }
        $iteracion = 'DETALLES_HM';
        $templateWord->setValue('DETALLES_H','${'.$iteracion.'}',1);
        $templateWord->setValue('DETALLES_H','${/'.$iteracion.'}',1);
        $templateWord->cloneBlock($iteracion,sizeof($hallazgo['valores']));
        rsort($hallazgo['valores']);
        foreach ($hallazgo['valores'] as $impacto) {
          if($impacto >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($impacto >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($impacto >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($impacto >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->fields('r', array('cvss_hallazgos'));
          $select->condition('impacto_hall_rev',(string)$impacto);
          $select->condition('id_rev_sitio',array_keys($sitios),'IN');
          $cvss = $select->execute()->fetchCol();
          $templateWord->setValue('nivel_impacto_n',$impacto,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('cvss_hallazgo',$cvss[0],1);
        }
      }
    }
    //Recomendaciones generales
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($solucion_corta));
    foreach ($solucion_corta as $solucion) {
      $templateWord->setValue('recomendación_general_h',$solucion,1);
    }
    Database::setActiveConnection();

    //Anexo B
    $contadorImg = 1;
    $templateWord->cloneBlock('ANEXOB',sizeof($datosSitios));
    foreach ($datosSitios as $sitio) {
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      $templateWord->setValue('idSitio',$sitio['ACT'],1);
      $templateWord->setValue('URL_SITIO',$url[0],1);
      $templateWord->setValue('DIR_IP',$ipSitio[0],1);
      //Se busca la cantidad de hallazgos por sitio
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $select->condition('estatus',true);
      $select->orderBy('impacto_hall_rev','DESC');
      $id_hall = $select->execute()->fetchCol();
      $cantidad = 'HALLAZGOBA';
      $templateWord->setValue('HALLAZGOB','${'.$cantidad.'}',1);
      $templateWord->setValue('HALLAZGOB','${/'.$cantidad.'}',1);
      $templateWord->cloneBlock($cantidad,sizeof($id_hall));
      foreach ($id_hall as $id) {
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
        $select->fields('r',array('id_rev_sitio_hall'));
        $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('r',array('impacto_hall_rev'));
        $select->fields('h',array('descripcion_hallazgo'));
        $select->fields('r',array('descripcion_hall_rev'));
        $select->fields('r',array('recursos_afectador'));
        $select->condition('r.id_hallazgo',(string)$id);
        $select->condition('id_rev_sitio',$sitio['id_rs']);
        $results = $select->execute();
        Database::setActiveConnection();
        $connection = \Drupal::service('database');
        foreach ($results as $result) {
          if($result->impacto_hall_rev >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($result->impacto_hall_rev >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($result->impacto_hall_rev >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($result->impacto_hall_rev >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $templateWord->setValue('nombre_hallazgo',$result->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('nivel_impacto_n',$result->impacto_hall_rev,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('descripcion_hallazgo',$result->descripcion_hallazgo,1);
          $templateWord->setValue('descripcion_hall_rev',$result->descripcion_hall_rev,1);
          $recursosAfectados = explode("\r", $result->recursos_afectador);
          $bloque = 'RECURSOS1';
          $templateWord->setValue('RECURSOS','${'.$bloque.'}',1);
          $templateWord->setValue('RECURSOS','${/'.$bloque.'}',1);
          $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
          foreach($recursosAfectados as $rec){
            $templateWord->setValue('recurso_afectado',$rec,1);
          }
          //Cantidad de imágenes
          $select = $connection->select('file_managed', 'fm');
          $select->addExpression('COUNT(id_rev_sh)','file_managed');
          $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
          $cantidadImagenesHallazgo = $select->execute()->fetchCol();
          $repImg = 'IMAGENESH';
          $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
          $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
            
          if($cantidadImagenesHallazgo[0]){
            $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
            //Imágenes y descripción
            $select = $connection->select('file_managed', 'fm')
              ->fields('fm', array('filename', 'descripcion'));
            $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
            $imagenesHallazgo = $select->execute();
            foreach ($imagenesHallazgo as $imagenH) {
              $templateWord->setImageValue('imagen',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
              $templateWord->setValue('contadorImg',$contadorImg,1);
              $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
              $contadorImg++;
            }
          }else{
            $templateWord->cloneBlock($repImg,0);
          }///
        }
      }
    }

    $templateWord->saveAs('reportes/helloWorld.docx');
    //*/







    //Reporte seguimiento uno o varios sitios
    $no_rev = 2;
    $fecha = getdate();
    if(strlen((string)$fecha['mon']) == 1){
      $mes = '0'.$fecha['mon'];
    }else{$mes = $fecha['mon'];}
    $nombreReporte = $fecha['year'] . $mes . '_';
    //Obtener url e ip sitio
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_sitio'));
    $select->condition('id_revision',$no_rev);
    $sitioId = $select->execute()->fetchCol();
    //url
    $select = Database::getConnection()->select('sitios', 'r');
    $select->fields('r', array('url_sitio'));
    $select->condition('id_sitio',$sitioId,'IN');
    $urlSitio = $select->execute()->fetchCol();
    //ip
    $select = Database::getConnection()->select('ip_sitios', 'i');
    $select->join('dir_ip','d','i.id_ip = d.id_ip');
    $select->fields('d', array('dir_ip_sitios'));
    $select->condition('id_sitio',$sitioId,'IN');
    $ipSitio = $select->execute()->fetchCol();

    if(sizeof($sitioId) == 1){
      $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaSeguimiento1.docx');
      $nombreReporte .= $urlSitio[0];
    }else{
      $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaSeguimiento2.docx');
      $nombreReporte .= 'VariosSitios';
    }
    ini_set("pcre.backtrack_limit", "-1");
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $connection = Database::getConnection();
    //Obtener usuarios
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$no_rev);
    $select->condition('seguimiento ',false);
    $usuarios_rev = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'pentester');
    $pentesters = $select->execute()->fetchCol();
    
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->join('user__roles','r','r.entity_id = u.uid');
    $select->fields('u', array('name'));
    $select->condition('uid', $usuarios_rev, 'IN');
    $select->condition('roles_target_id', 'coordinador de revisiones');
    $coordinador = $select->execute()->fetchCol();

    $templateWord->cloneBlock('PENTESTER',sizeof($pentesters));
    foreach ($pentesters as $nombre) {
      $templateWord->setValue('nombre_pentester',$nombre,1);
    }
    $templateWord->setValue('nombre_revisor',$coordinador[0]);
    $templateWord->setValue('nombre_visto_bueno',$coordinador[0]);
    
    switch ($fecha['mon']) {
      case 1:
        $mes = 'Enero';
        break;
      case 2:
        $mes = 'Febrero';
        break;
      case 3:
        $mes = 'Marzo';
        break;
      case 4:
        $mes = 'Abril';
        break;
      case 5:
        $mes = 'Mayo';
        break;
      case 6:
        $mes = 'Junio';
        break;
      case 7:
        $mes = 'Julio';
        break;
      case 8:
        $mes = 'Agosto';
        break;
      case 9:
        $mes = 'Septiempbre';
        break;
      case 10:
        $mes = 'Octubre';
        break;
      case 11:
        $mes = 'Noviembre';
        break;
      case 12:
        $mes = 'Diciembre';
        break;
      default:
        $mes = 'XXXX';
        break;
    }
    $templateWord->setValue('F_DIA',$fecha['mday']);
    $templateWord->setValue('MES',$mes);
    $templateWord->setValue('ANIO',$fecha['year']);
    $templateWord->setValue('F_MES',$mes);
    $templateWord->setValue('F_ANIO',$fecha['year']);
    if(sizeof($sitioId) == 1){
      $templateWord->setValue('URL_SITIO',$urlSitio[0]);
      $templateWord->setValue('DIR_IP',$ipSitio[0]);
    }
    
    //Tipo de revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('tipo_revision'));
    $select->condition('id_revision',$no_rev);
    $tipo = $select->execute()->fetchCol();
    if($tipo[0]){$tipoR = 'Circular';}else{$tipoR = 'Oficio';}
    //$nombreReporte .= $urlSitio[0].'_REV'.$no_rev.'_'.$tipoR.'_Seguimiento';
    $nombreReporte .= '_REV'.$no_rev.'_'.$tipoR.'_Seguimiento.docx';

    //Se ordenan los sitios y hallazgos
    $select = Database::getConnection()->select('revisiones_sitios', 'r');
    $select->fields('r', array('id_rev_sitio'));
    $select->condition('id_revision',$no_rev);
    $id_rev_sitio = $select->execute()->fetchCol();
    $sitios = array();
    $hallazgos = array();
    $hall_ord = array();
    foreach ($id_rev_sitio as $sitio) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$sitio);
      $impactos = $select->execute();
      $impacto = 0;
      foreach ($impactos as $valor) {
        if($valor > $impacto){
          $impacto = $valor->impacto_hall_rev;
        }
        if(!in_array($dato->id_hallazgo, $hallazgos)) {
          array_push($hallazgos, $valor->id_hallazgo);
        }
      }
      $sitios[$sitio] = $impacto;
    }
    arsort($sitios);//id_rev_sitio
    foreach ($hallazgos as $idhallazgo) {
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $select->orderBy('impacto_hall_rev','DESC');
      $impactos = $select->execute()->fetchCol();
      $valor = 0;
      foreach ($impactos as $impacto) {
        if($impacto > $valor){
          $valor = $impacto;
        }
      }
      $hall_ord[$idhallazgo] = $valor;
    }
    arsort($hall_ord);

    //Se organizan los sitios por id
    $datosSitios = array();
    $contadorSitios = 1;
    $alcance = array();
    $resumenEjecutivo1 = array();
    $resumenEjecutivo2 = array();
    foreach ($sitios as $id_rs => $valor) {
      if(strlen((string)$contadorSitios) == 1){
        $sitID = '0'.$contadorSitios;
      }
      $select = Database::getConnection()->select('revisiones_sitios', 'r');
      $select->fields('r', array('id_sitio'));
      $select->condition('id_rev_sitio',$id_rs);
      $id_sitio = $select->execute()->fetchCol();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$id_sitio[0]);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$id_sitio[0]);
      $ipSitio = $select->execute()->fetchCol();
      array_push($datosSitios, array(
        'ACT' => $sitID,
        'id_s' => $id_sitio[0],
        'id_rs' => $id_rs,
      ));
      $c_critico = 0;
      $c_alto = 0;
      $c_medio = 0;
      $c_bajo = 0;
      $c_ni = 0;
      $c_criticoP = 0;
      $c_altoP = 0;
      $c_medioP = 0;
      $c_bajoP = 0;
      $c_niP = 0;
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $select->condition('estatus',true);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_criticoP++; }
        elseif ($valor >= 7.0) { $c_altoP++; }
        elseif ($valor >= 4.0) { $c_medioP++; }
        elseif ($valor >= 0.1) { $c_bajoP++; }
        else{ $c_ni++; }
      }
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r',array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',$id_rs);
      $select->condition('estatus',false);
      $impactos = $select->execute()->fetchCol();
      foreach ($impactos as $valor) {
        if($valor >= 9.0){ $c_critico++; }
        elseif ($valor >= 7.0) { $c_alto++; }
        elseif ($valor >= 4.0) { $c_medio++; }
        elseif ($valor >= 0.1) { $c_bajo++; }
        else{ $c_ni++; }
      }
      array_push($resumenEjecutivo1,array(
        'id_activoR1' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_criticoP' => $c_critico,
        'c_altoP' => $c_alto,
        'c_medioP' => $c_medio,
        'c_bajoP' => $c_bajo,
        'c_niP' => $c_ni,
      ));
      array_push($resumenEjecutivo2,array(
        'id_activoR2' => $sitID,
        'SITIO_WEB' => $url[0],
        'c_critico' => $c_criticoP,
        'c_alto' => $c_altoP,
        'c_medio' => $c_medioP,
        'c_bajo' => $c_bajoP,
        'c_ni' => $c_niP,
      ));
      array_push($alcance,array(
        'id_activoA' => $sitID,
        'SITIO_WEB' => $url[0],
        'dir_IP' => $ipSitio[0],
      ));
      $contadorSitios++;
    }
    //Tabla de alcance y resumen ejecutivo
    $templateWord->cloneRowAndSetValues('id_activoR1', $resumenEjecutivo1);
    $templateWord->cloneRowAndSetValues('id_activoR2', $resumenEjecutivo2);
    $templateWord->cloneRowAndSetValues('id_activoA', $alcance);

    //Se organizan los hallazgos con id y valores
    $contadorHallazgo = 1;
    $datosHallazgos = array();
    foreach ($hall_ord as $idhallazgo => $valor) {
      if(strlen((string)$contadorHallazgo) == 1){
        $recID = '0'.$contadorHallazgo;
      }
      $valores = array();
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('impacto_hall_rev'));
      $select->condition('id_rev_sitio',array_keys($sitios),'IN');
      $select->condition('id_hallazgo',$idhallazgo);
      $select->orderBy('impacto_hall_rev','DESC');
      $impacto = $select->execute()->fetchCol();
      foreach ($impacto as $value) {
        if(!array_key_exists($value,$valores)){
          array_push($valores, $value);
        }
      }
      array_push($datosHallazgos, array(
        'ID' => 'REC'.$recID,
        'id_hall' => $idhallazgo,
        'valores' => $valores,
      ));
      $contadorHallazgo++;
    }

    //Estado actual de seguridad
    $estadoActual = array();
    $repetirAA = array();
    $anexoAtimes = 0;
    foreach ($datosSitios as $sitio) {
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      //id_hallazgos
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $select->orderBy('impacto_hall_rev','DESC');
      $id_h = $select->execute()->fetchCol();
      foreach ($datosHallazgos as $hallazgo) {
        if(in_array($hallazgo['id_hall'], $id_h)){
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
          $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
          $select->fields('r', array('impacto_hall_rev'));
          $select->fields('r', array('estatus'));
          $select->condition('id_rev_sitio',$sitio['id_rs']);
          $select->condition('r.id_hallazgo',$hallazgo['id_hall']);
          $select->orderBy('impacto_hall_rev','DESC');
          $informacion = $select->execute();
          foreach ($informacion as $info) {
            if($info->impacto_hall_rev >= 9.0){
              $nivelH = 'CRÍTICO';
            }elseif ($info->impacto_hall_rev >= 7.0) {
              $nivelH = 'ALTO';
            }elseif ($info->impacto_hall_rev >= 4.0) {
              $nivelH = 'MEDIO';
            }elseif ($info->impacto_hall_rev >= 0.1) {
              $nivelH = 'BAJO';
            }else{
              $nivelH = 'SIN IMPACTO';
            }
            if($info->estatus){
              array_push($estadoActual,array(
                'id_activoE' => $sitio['ACT'],
                'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
                'nombre_hallazgo' => $info->nombre_hallazgo_vulnerabilidad,
                'rec_id' => $hallazgo['ID'],
                'nivel_impacto_n' => $info->impacto_hall_rev,
                'nivel_impacto' => $nivelH,
                'estatus' => 'PERSISTENTE',
              ));
              //$anexoAtimes++;
              if(!in_array($hallazgo['ID'], $repetirAA)){array_push($repetirAA, $hallazgo['ID']);}
            }else{
              array_push($estadoActual,array(
                'id_activoE' => $sitio['ACT'],
                'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
                'nombre_hallazgo' => '-',
                'rec_id' => '-',
                'nivel_impacto_n' => '-',
                'nivel_impacto' => '-',
                'estatus' => 'MITIGADO',
              ));
            }
          }
        }
      }      
    }
    $templateWord->cloneRowAndSetValues('id_activoE', $estadoActual);

    $solucion_corta = array();
    //Anexo A
    $templateWord->cloneBlock('TABLA_H',sizeof($repetirAA));
    foreach ($datosHallazgos as $hallazgo) {
      //Se revisa el estatus
      $select = Database::getConnection()->select('revisiones_hallazgos', 'h');
      $select->join('revisiones_sitios','r','r.id_rev_sitio = h.id_rev_sitio');
      $select->fields('h', array('estatus'));
      $select->condition('id_hallazgo',$hallazgo['id_hall']);
      $select->condition('id_revision',$no_rev);
      $estado = $select->execute()->fetchCol();
      //Si es persistente, se realiza
      if($estado[0]){
        $templateWord->setValue('REC',$hallazgo['ID'],1);
        $select = Database::getConnection()->select('hallazgos', 'h');
        $select->fields('h', array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('h', array('descripcion_hallazgo'));
        $select->fields('h', array('solucion_recomendacion_halazgo'));
        $select->fields('h', array('referencias_hallazgo'));
        $select->fields('h', array('solucion_corta'));
        $select->condition('h.id_hallazgo',$hallazgo['id_hall']);
        $datos = $select->execute();
        foreach ($datos as $dato) {
          $templateWord->setValue('nombre_hallazgo',$dato->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('descripcion_hallazgo',$dato->descripcion_hallazgo,1);
          $templateWord->setValue('recomendacionG_hallazgo',$dato->solucion_recomendacion_halazgo,1);
          $templateWord->setValue('url_hallazgo',$dato->referencias_hallazgo,1);
          if(!in_array($dato->solucion_corta, $solucion_corta)){array_push($solucion_corta, $dato->solucion_corta);}
        }
        $iteracion = 'DETALLES_HM';
        $templateWord->setValue('DETALLES_H','${'.$iteracion.'}',1);
        $templateWord->setValue('DETALLES_H','${/'.$iteracion.'}',1);
        $templateWord->cloneBlock($iteracion,sizeof($hallazgo['valores']));
        rsort($hallazgo['valores']);
        foreach ($hallazgo['valores'] as $impacto) {
          if($impacto >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($impacto >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($impacto >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($impacto >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
          $select->fields('r', array('cvss_hallazgos'));
          $select->condition('impacto_hall_rev',(string)$impacto);
          $select->condition('id_rev_sitio',array_keys($sitios),'IN');
          $cvss = $select->execute()->fetchCol();
          $templateWord->setValue('nivel_impacto_n',$impacto,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('cvss_hallazgo',$cvss[0],1);
        }
      }
    }
    //Recomendaciones generales
    $templateWord->cloneBlock('RECOMENDACIONES',sizeof($solucion_corta));
    foreach ($solucion_corta as $solucion) {
      $templateWord->setValue('recomendación_general_h',$solucion,1);
    }
    Database::setActiveConnection();

    //Anexo B
    $contadorImg = 1;
    $templateWord->cloneBlock('ANEXOB',sizeof($datosSitios));
    foreach ($datosSitios as $sitio) {
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      //url
      $select = Database::getConnection()->select('sitios', 's');
      $select->fields('s', array('url_sitio'));
      $select->condition('id_sitio',$sitio['id_s']);
      $url = $select->execute()->fetchCol();
      //ip
      $select = Database::getConnection()->select('ip_sitios', 'i');
      $select->join('dir_ip','d','i.id_ip = d.id_ip');
      $select->fields('d', array('dir_ip_sitios'));
      $select->condition('id_sitio',$sitio['id_s']);
      $ipSitio = $select->execute()->fetchCol();
      $templateWord->setValue('idSitio',$sitio['ACT'],1);
      $templateWord->setValue('URL_SITIO',$url[0],1);
      $templateWord->setValue('DIR_IP',$ipSitio[0],1);
      //Se busca la cantidad de hallazgos por sitio
      $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
      $select->fields('r', array('id_hallazgo'));
      $select->condition('id_rev_sitio',$sitio['id_rs']);
      $select->condition('estatus',true);
      $select->orderBy('impacto_hall_rev','DESC');
      $id_hall = $select->execute()->fetchCol();
      $cantidad = 'HALLAZGOBA';
      $templateWord->setValue('HALLAZGOB','${'.$cantidad.'}',1);
      $templateWord->setValue('HALLAZGOB','${/'.$cantidad.'}',1);
      $templateWord->cloneBlock($cantidad,sizeof($id_hall));
      foreach ($id_hall as $id) {
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_hallazgos', 'r');
        $select->join('hallazgos','h','h.id_hallazgo = r.id_hallazgo');
        $select->fields('r',array('id_rev_sitio_hall'));
        $select->fields('h',array('nombre_hallazgo_vulnerabilidad'));
        $select->fields('r',array('impacto_hall_rev'));
        $select->fields('h',array('descripcion_hallazgo'));
        $select->fields('r',array('descripcion_hall_rev'));
        $select->fields('r',array('recursos_afectador'));
        $select->condition('r.id_hallazgo',(string)$id);
        $select->condition('id_rev_sitio',$sitio['id_rs']);
        $results = $select->execute();
        Database::setActiveConnection();
        $connection = \Drupal::service('database');
        foreach ($results as $result) {
          if($result->impacto_hall_rev >= 9.0){
            $nivelH = 'CRÍTICO';
          }elseif ($result->impacto_hall_rev >= 7.0) {
            $nivelH = 'ALTO';
          }elseif ($result->impacto_hall_rev >= 4.0) {
            $nivelH = 'MEDIO';
          }elseif ($result->impacto_hall_rev >= 0.1) {
            $nivelH = 'BAJO';
          }else{
            $nivelH = 'SIN IMPACTO';
          }
          $templateWord->setValue('nombre_hallazgo',$result->nombre_hallazgo_vulnerabilidad,1);
          $templateWord->setValue('nivel_impacto_n',$result->impacto_hall_rev,1);
          $templateWord->setValue('nivel_impacto',$nivelH,1);
          $templateWord->setValue('descripcion_hallazgo',$result->descripcion_hallazgo,1);
          $templateWord->setValue('descripcion_hall_rev',$result->descripcion_hall_rev,1);
          $recursosAfectados = explode("\r", $result->recursos_afectador);
          $bloque = 'RECURSOS1';
          $templateWord->setValue('RECURSOS','${'.$bloque.'}',1);
          $templateWord->setValue('RECURSOS','${/'.$bloque.'}',1);
          $templateWord->cloneBlock($bloque, sizeof($recursosAfectados));
          foreach($recursosAfectados as $rec){
            $templateWord->setValue('recurso_afectado',$rec,1);
          }
          //Cantidad de imágenes
          $select = $connection->select('file_managed', 'fm');
          $select->addExpression('COUNT(id_rev_sh)','file_managed');
          $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
          $cantidadImagenesHallazgo = $select->execute()->fetchCol();
          $repImg = 'IMAGENESH';
          $templateWord->setValue('IMAGENES','${'.$repImg.'}',1);
          $templateWord->setValue('IMAGENES','${/'.$repImg.'}',1);
            
          if($cantidadImagenesHallazgo[0]){
            $templateWord->cloneBlock($repImg,$cantidadImagenesHallazgo[0]);
            //Imágenes y descripción
            $select = $connection->select('file_managed', 'fm')
              ->fields('fm', array('filename', 'descripcion'));
            $select->condition('id_rev_sh', $result->id_rev_sitio_hall);
            $imagenesHallazgo = $select->execute();
            foreach ($imagenesHallazgo as $imagenH) {
              $templateWord->setImageValue('imagen',array('path' => 'sites/default/files/content/evidencia/'.$imagenH->filename, 'width' => 500, 'height' => 250, 'ratio' => false),1);
              $templateWord->setValue('contadorImg',$contadorImg,1);
              $templateWord->setValue('descripcion_img',$imagenH->descripcion,1);
              $contadorImg++;
            }
          }else{
            $templateWord->cloneBlock($repImg,0);
          }///
        }
      }
    }

    //$templateWord->saveAs('reportes/'.$nombreReporte);
    $templateWord->saveAs('reportes/helloWorld.docx');
    //*/











    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    //$messenger_service->addMessage($mensaje);
  }
}
    //Reporte completo varios sitios