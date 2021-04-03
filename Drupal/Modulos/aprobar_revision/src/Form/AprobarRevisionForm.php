<?php
/*
 * @file
 * Contains \Drupal\aprobar_revision\Form\AprobarRevisionForm
 */
namespace Drupal\aprobar_revision\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/*
 *
 */
class AprobarRevisionForm extends FormBase{
  /*
   * (@inheritdoc)
   */
  public function getFormId(){
    return 'aprobar_revision_form';
  }
  /*
   * (@inheritdoc)
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rev_id = NULL){
    //Comprobación de que el usuario loggeado tiene permiso de ver esta revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$rev_id);
    $results = $select->execute()->fetchCol();
    //estatus_revision
    $select = Database::getConnection()->select('actividad', 'a');
    $select->addExpression('MAX(id_estatus)','actividad');
    $select->condition('id_revision',$rev_id);
    $estatus = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $current_user_roles = \Drupal::currentUser()->getRoles();
    if (!in_array(\Drupal::currentUser()->id(), $results) || !in_array('coordinador de revisiones', $current_user_roles) || $estatus[0] != 3){
      return array('#markup' => "No tienes permiso para ver esta página.",);
    }
    global $no_rev;
    global $seguimiento;
    $no_rev = $rev_id;
    //Se busca si es revisión de seguimiento o no
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r', array('seguimiento'));
    $select->condition('id_revision',$rev_id);
    $seg = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $seguimiento = $seg[0];
    if($seg[0] != 0){
      $form['text'] = array(
        '#type' => 'item',
        '#title' => '¿Deseas aprobar esta revision de seguimiento?',
        '#markup' => 'ID revisión: '. $rev_id,
      );
    }else{
      $form['text'] = array(
        '#type' => 'item',
        '#title' => '¿Deseas aprobar esta revision?',
        '#markup' => 'ID revisión: '. $rev_id,
      );}
    $form['aprobar'] = array(
      '#type' => 'submit',
      '#value' => t('Aprobar'),
    );
    if($seg[0] != 0){
      $url = Url::fromRoute('informacion_seguimiento.content', array('rev_id' => $rev_id));
    }else{
      $url = Url::fromRoute('informacion_revision.content', array('rev_id' => $rev_id));
    }
    $cancelar = Link::fromTextAndUrl('Cancelar', $url);
    $cancelar = $cancelar->toRenderable();
    $cancelar['#attributes'] = array('class' => array('button'));
    $form['cancelar'] = array('#markup' => render($cancelar),);
  	return $form;
  }
  /*
   * (@inheritdoc)
   */
  public function submitForm(array &$form, FormStateInterface $form_state){
    global $no_rev;
    global $seguimiento;
    $revision_actual = $no_rev;
    $mensaje = 'Revision aprobada. Se notificará a los pentesters por correo electrónico.';
    //Consulta de correos
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones_asignadas', 'r');
    $select->fields('r', array('id_usuario'));
    $select->condition('id_revision',$no_rev);
    $select->condition('id_usuario',\Drupal::currentUser()->id(),'<>');
    $id_pentester = $select->execute()->fetchCol();
    Database::setActiveConnection();
    $select = Database::getConnection()->select('users_field_data', 'u');
    $select->fields('u', array('mail'));
    $select->condition('uid',$id_pentester, 'IN');
    $correos = $select->execute()->fetchCol();
    //Envio de correo
    $to = "";
    foreach($correos as $mail){ $to .= $mail.',';}
    $to = substr($to, 0, -1);
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $params['context']['subject'] = "Asignación de revisión";
    $params['context']['message'] = 'El Coordinador de revisiones ha aprobado la revision '. $no_rev.'.';
    //$to = 'mauricio@dominio.com,angel@dominio.com';
    $email = \Drupal::service('plugin.manager.mail')->mail('system', 'mail', $to, $langcode, $params);
    if(!$email){$mensaje .= " Ocurrió algún error y no se ha podido enviar el correo de notificación.";}
    //Actualizacion de estatus de la revision
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    //////////////////////////////////////////////
    $fecha = getdate();
    $hoy = $fecha['year'].'-'.$fecha['mon'].'-'.$fecha['mday'];
    $result = $connection->insert('actividad')
      ->fields(array(
        'id_revision' => $no_rev,
        'id_estatus' => 4,
        'fecha' => $hoy,
      ))->execute();
    //Se relacionan los nuevos hallazgos con la revisión original
    if($seguimiento){
      $select = Database::getConnection()->select('revisiones', 'r');
      $select->fields('r', array('id_seguimiento'));
      $select->condition('id_revision',$no_rev);
      $id_seguimiento = $select->execute()->fetchCol();
      $select = Database::getConnection()->select('revisiones_sitios', 'r');
      $select->fields('r', array('id_rev_sitio'));
      $select->fields('r', array('id_sitio'));
      $select->condition('id_revision',$id_seguimiento[0]);
      $original = $select->execute();
      foreach ($original as $dato) {
        $select = Database::getConnection()->select('revisiones_sitios', 'r');
        $select->fields('r', array('id_rev_sitio'));
        $select->fields('r', array('id_sitio'));
        $select->condition('id_revision',$no_rev);
        $nuevos = $select->execute();
        foreach ($nuevos as $datoN) {
          if($datoN->id_sitio == $dato->id_sitio){
            //Modificación en la BD
            $update = $connection->update('revisiones_hallazgos')
              ->fields(array(
                'id_rev_sitio' => $dato->id_rev_sitio,
              ))
              ->condition('id_rev_sitio',$datoN->id_rev_sitio)
              ->execute();
          }
        }
      }
      $no_rev = $id_seguimiento[0];
    }
    Database::setActiveConnection();
    //En esta parte se crea el reporte
    //Se obtiene el tipo y la cantidad de sitios
    Database::setActiveConnection('drupaldb_segundo');
    $connection = Database::getConnection();
    $select = Database::getConnection()->select('revisiones', 'r');
    $select->fields('r',array('tipo_revision'));
    $select->condition('id_revision',$no_rev);
    $tipo_revision = $select->execute()->fetchCol();
    Database::setActiveConnection();
    //////////////////////////////////////////////
    if(!$seguimiento){
      //If reporte tipo corto (circular) tipo_revision == true
      if($tipo_revision[0]){
        $fecha = getdate();
        $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaCorto.docx');
        ini_set("pcre.backtrack_limit", "-1");
        //$no_rev = 1;
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        //Obtener usuarios
        $select = Database::getConnection()->select('revisiones_asignadas', 'r');
        $select->fields('r', array('id_usuario'));
        $select->condition('id_revision',$no_rev);
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
        $fecha = getdate();
        $fecha_fin = $fecha['year'].'-'.$fecha['mon'].'-'.$fecha['mday'];

        $templateWord->setValue('sitio_web',$urlSitio[0]);
        $templateWord->setValue('dir_ip',$ipSitio[0]);
        $templateWord->setValue('fecha_fin_revision',$fecha_fin);
        $templateWord->setValue('fecha_hoy',$fecha['year']);
        if(strlen((string)$fecha['mon']) == 1){
          $mes = '0'.$fecha['mon'];
        }else{$mes = $fecha['mon'];}

        $nombreArchivo = $fecha['year'].$mes.'_'.$urlSitio[0].'_REV'.$no_rev.'_Circular';

        $c_critico = 0;
        $c_alto = 0;
        $c_medio = 0;
        $c_bajo = 0;
        $c_ni = 0;
        //hallazgos relacionados
        $select = Database::getConnection()->select('revisiones_sitios', 'r');
        $select->join('revisiones_hallazgos','h','r.id_rev_sitio = h.id_rev_sitio');
        $select->fields('h', array('id_hallazgo'));
        $select->condition('id_revision',$no_rev);
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
        $select->condition('id_revision',$no_rev);
        $select->orderBy('impacto_hall_rev','DESC');
        $idHallazgos = $select->execute();
        $templateWord->setValue('NOMBRE_HALLAZGO','Actualizar manualmente',1);
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
            $templateWord->setValue('NOMBRE_HALLAZGO',$contadorHallazgo . '. ' . $dato->nombre_hallazgo_vulnerabilidad,1);
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
        
        $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');
      }else{
        Database::setActiveConnection('drupaldb_segundo');
        $connection = Database::getConnection();
        $select = Database::getConnection()->select('revisiones_sitios', 'r');
        $select->addExpression('COUNT(id_sitio)','revisiones_sitios');
        $select->condition('id_revision',$no_rev);
        $cantidad = $select->execute()->fetchCol();
        //reporte tipo completo (oficio) un sitio
        if($cantidad[0] == 1){
          $fecha = getdate();
          if(strlen((string)$fecha['mon']) == 1){
            $mes = '0'.$fecha['mon'];
          }else{$mes = $fecha['mon'];}
          $nombreArchivo = $fecha['year'] . $mes . '_';

          $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes//plantillas/plantillaCompleto1.docx');
          //$no_rev = 2;
          ini_set("pcre.backtrack_limit", "-1");
          Database::setActiveConnection('drupaldb_segundo');
          $connection = Database::getConnection();
          $connection = Database::getConnection();
          //Obtener usuarios
          $select = Database::getConnection()->select('revisiones_asignadas', 'r');
          $select->fields('r', array('id_usuario'));
          $select->condition('id_revision',$no_rev);
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
          $templateWord->setValue('DIA',$fecha['mday']);
          $templateWord->setValue('MES',$mes);
          $templateWord->setValue('ANIO',$fecha['year']);
          $templateWord->setValue('SITIO_WEB',$urlSitio[0]);
          $templateWord->setValue('DIR_IP',$ipSitio[0]);
          
          $nombreArchivo .= $urlSitio[0].'_REV'.$no_rev.'_Oficio';

          $select = Database::getConnection()->select('revisiones_sitios', 's');
          $select->join('revisiones_hallazgos','h','s.id_rev_sitio = h.id_rev_sitio');
          $select->fields('h', array('id_rev_sitio_hall'));
          $select->condition('id_revision',$no_rev);
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
            }else{
              $templateWord->cloneBlock($repImg,0);
            }
            $contadorHallazgo++;
          }
          
          $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');
        }else{
          $fecha = getdate();
          if(strlen((string)$fecha['mon']) == 1){
            $mes = '0'.$fecha['mon'];
          }else{$mes = $fecha['mon'];}
          $nombreArchivo = $fecha['year'] . $mes . '_';
          
          $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaCompleto2.docx');
          //$no_rev = 3;
          ini_set("pcre.backtrack_limit", "-1");
          Database::setActiveConnection('drupaldb_segundo');
          $connection = Database::getConnection();
          $connection = Database::getConnection();
          //Obtener usuarios
          $select = Database::getConnection()->select('revisiones_asignadas', 'r');
          $select->fields('r', array('id_usuario'));
          $select->condition('id_revision',$no_rev);
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
          
          $nombreArchivo .= 'variosSitios_REV'.$no_rev.'_Oficio';
          
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
                    'ACTIVO' => $url[0] . ' / ' . $ipSitio[0],
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
                }else{
                  $templateWord->cloneBlock($repImg,0);
                }
              }
            }
          }
          $templateWord->saveAs('reportes/'.$nombreArchivo.'.docx');
        }
        Database::setActiveConnection();
      }
    }else{
      $fecha = getdate();
      if(strlen((string)$fecha['mon']) == 1){
        $mes = '0'.$fecha['mon'];
      }else{$mes = $fecha['mon'];}
      $nombreArchivo = $fecha['year'] . $mes . '_';
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
        $nombreArchivo .= $urlSitio[0];
      }else{
        $templateWord = new \PhpOffice\PhpWord\TemplateProcessor('reportes/plantillas/plantillaSeguimiento2.docx');
        $nombreArchivo .= 'VariosSitios';
      }
      ini_set("pcre.backtrack_limit", "-1");
      Database::setActiveConnection('drupaldb_segundo');
      $connection = Database::getConnection();
      $connection = Database::getConnection();
      //Obtener usuarios
      $select = Database::getConnection()->select('revisiones_asignadas', 'r');
      $select->fields('r', array('id_usuario'));
      $select->condition('id_revision',$no_rev);
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
      $nombreArchivo .= '_REV'.$revision_actual.'_'.$tipoR.'_seguimiento.docx';

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
                  'nombre_hallazgo' => $info->nombre_hallazgo_vulnerabilidad,
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

      $templateWord->saveAs('reportes/'.$nombreArchivo);
    }
    //////////////////////////////////////////////
    $mensaje = " Reporte generado como '" . $nombreArchivo . "'.";
    //*/
    $messenger_service = \Drupal::service('messenger');
    $messenger_service->addMessage($mensaje);
    $form_state->setRedirectUrl(Url::fromRoute('revisiones_aprobadas.content'));
  }
}