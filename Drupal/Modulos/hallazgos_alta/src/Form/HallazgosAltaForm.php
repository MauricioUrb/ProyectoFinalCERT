<?php
/**
 * @file
 * Contains \Drupal\hallazgos_alta\Form\HallazgosAltaForm
 */
namespace Drupal\hallazgos_alta\Form;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

// para validaciones en la entrada de los datos
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

// para lo del csv
// composer require phpoffice/phpspreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/*
 * Provides a RSVP Email form.
 */
class HallazgosAltaForm extends FormBase {
 	/*
  	 * {@inheritdoc}
	 */
	public function getFormId() {
		return 'hallazgos_alta_form';
	}

	/*
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {

		$form_state->disableCache();
		$form['browser'] = array(
			'#type' => 'fieldset', 
			'#title' => t('En esta página puede dar de alta un nuevo hallazgo.'), 
			'#collapsible' => TRUE, 
			'#description' => t("Llene los campos solicitados o cargue un archivo CSV"), 	
		); 

		$form['name'] = array(
			'#type' => 'textfield',
			'#title' => 'Nombre del hallazgo/vulnerabilidad',
      			'#size' => 60,
    		);
		$form['description'] = array(
			'#title' => t('Description'),
      			'#type' => 'textarea',
      			'#description' => t('Descripción de la vulnerabilidad/hallazgo'),
      			'#size' => 60,
    		);
    		$form['solution'] = array(
      			'#type' => 'textarea',
      			'#title' => t('Solución/Recomendación'),
      			'#description' => t('Solución o recomendación a la vulnerabilidad/hallazgo'),
    		);
    		$form['references'] = array(
      			'#title' => t('Referencias'),
      			'#type' => 'textarea',
      			'#size' => 60,
    		);
    		//aqui falta el catalogo para el impacto
    		$form['cvss_vector'] = array(
      			'#title' => t('Vector CVSS'),
      			'#type' => 'textfield',
      			'#maxlength' => 108,
      			'#size' => 100,
			'#description' => t('Vector CVSS de la vulnerabilidad'),
    		);
		$form['level'] = array(
			'#type' => 'select',
			'#title' => t("Nivel de la vulnerabilidad"),
			'#options' => array(
				'CRITICO' => 'CRITICO',
				'ALTO' => 'ALTO',
				'MEDIO' => 'MEDIO',
				'BAJO' => 'BAJO',
			),
			'#description' => t('Selecciona el nivel de la vulnerabilidad'),
		);
		$form['level_score'] = array(
      			'#type' => 'textfield',
			'#description' => t('Ingresa el score de la vulnerabilidad'),
      			'#size' => 10,
		);
    		$form['cvss_link'] = array(
      			'#title' => t('Enlace'),
			'#type' => 'textfield',
		      	'#maxlength' => 159,
      			'#size' => 60,
    		);
    		$form['resume'] = array(
      			'#title' => t('Resumen Ejecutivo'),
      			'#type' => 'textarea',
      			'#description' => t('Descripción de alto nivel, es decir, resumen ejecutivo'),
      			'#size' => 60,
    		);
    		$form['recomendation'] = array(
      			'#title' => t('Recomendación general'),
      			'#type' => 'textarea',
      			'#size' => 60,
   	 	);
		$form['csv_file'] = array(
			'#type' => 'managed_file',
			'#title' => t('Archivo CSV.'),
			'#description' => t('Cargar desde archivo CSV '),
			'#upload_validators' => array(
				'file_validate_extensions' => array('csv'),
			),
			'#upload_location' => 'public://content/csv_files/alta_hallazgos/',
		);
    		$form['alta'] = array(
      			'#type' => 'submit',
			'#value' => t('Dar de alta'),
			'#name' => 'alta',
    		);
		$form['alta_file'] = array(
			'#type' => 'submit',
			'#value' => t('Cargar archivo'),
			'#name' => 'alta_file',
		);
   		return $form;
	}

	/*
	 *
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$messenger_service = \Drupal::service('messenger');
		$msg_1 = "El campo de";
		$msg_2 = "no puede estar vacío";
		$button_clicked = $form_state->getTriggeringElement()['#name'];
		$options = ['name', 'description', 'solution', 'references', 'cvss_vector', 'cvss_link', 'resume', 'recomendation', 'level_score'];
		$vector = $form_state->getValue("cvss_vector");
		$score = $form_state->getValue("level_score");
		$level = $form_state->getValue("level");
		if($button_clicked == 'alta'){
			foreach($options as $option){
	       	        	// valida que los campos no esten vacios
				if(strlen($form_state->getValue("$option")) <= 0) {	
					$form_state->setErrorByName("$option", $this->t("$msg_1 '$option' $msg_2"));
				} else if($option == "cvss_vector"){
					// expresion regular para validar el vector
					$reg_ex = '/AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?/';
					// validacion del vetor
					if(!preg_match($reg_ex, $vector)){
						$form_state->setErrorByName("$option", $this->t("El vector ingresado es invalido"));
					} 
				} else if($option == "level_score"){
					// validamos que el valor sea un flotante entre 0.0 y 10.0
					if(is_numeric($score) && ($score >= 0.0 &&  $score <= 10.0)){
						$msg_vul = 'El puntaje para la vulnerabilidad';
						// validamos el score acorde a su nivel
						switch($level){
						case "CRITICO":
							$rango = 'es entre 9.0 y 10.0';
							if(!($score <= 10.0 && $score >= 9.0)){
								$form_state->setErrorByName("$option", $this->t("$msg_vul CRITICA $rango"));
							}
							break;
						case "ALTO":
							$rango = 'es entre 7.0 y 8.9';
							if(!($score < 9 && $score >= 7)){
								$form_state->setErrorByName("$option", $this->t("$msg_vul ALTA $rango"));
							}
							break;
						case "MEDIO":
							$rango = 'es entre 4.0 y 6.9';
							if(!($score < 7 && $score >= 4)){
								$form_state->setErrorByName("$option", $this->t("$msg_vul MEDIA $rango"));
							}
							break;
						case "BAJO":
							$rango = 'es entre 0.1 y 3.9';
							if(!($score < 4 && $score > 0)){
								$form_state->setErrorByName("$option", $this->t("$msg_vul BAJA $rango"));
							}
							break;
						}
					} else {
						$form_state->setErrorByName("level_score", $this->t("Ingresa un número menor o igual a 10 y mayor a 0, ej. 5.6"));
					}
				}
			}

		} else if($button_clicked == 'alta_file'){
			// se valida que se haya cargado algun archivo(esto ya funciona)
			if ($form_state->getValue('csv_file') == NULL) {
				$form_state->setErrorByName('csv_file', $this->t('No se puede subir el archivo seleccionado'));
			}
		}
	}

	/**
	 * {@inheritdoc}
   	 * Se hace el insert a la bases de datos
   	*/
	public function submitForm(array &$form, FormStateInterface $form_state) {
		// nos conectamos a la base de datos secundaria
		\Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
		$connection = \Drupal\Core\Database\Database::getConnection();

		$messenger_service = \Drupal::service('messenger');

		if($form_state->getTriggeringElement()['#name'] == 'alta_file'){
			// obtenemos el nombre del archivo      
			$file_csv = $form_state->getValue('csv_file');
			$file_content = \Drupal\file\Entity\File::load($file_csv[0]);

			// guardar el archivo de manera permanente en el servidor 
			//$file_content->setPermanent();
			//$file_content->save();
      
			$file_uri = $file_content->getFileUri();
			$stream_wrapper_manager = \Drupal::service('stream_wrapper_manager')->getViaUri($file_uri);
			$file_path = $stream_wrapper_manager->realpath();
      
			// recibe el path completo del archivo
			$spreadsheet = IOFactory::load($file_path);
			$sheetData = $spreadsheet->getActiveSheet();
			$rows = array();
			foreach ($sheetData->getRowIterator() as $row) {  
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(FALSE); 
				$cells = [];
				foreach ($cellIterator as $cell) {
					$cells[] = $cell->getValue();
				}
				$rows[] = $cells; 
			}

			// esto es para quitar el renglon con los elementos ej. nombre,descripcion,sitio,...
			array_shift($rows);
      
			$cont = 0;
			// aqui se debe de hacer el insert
			foreach($rows as $row){
				// iniciamos la transaccion por temas de seguridad
				// transaccion en caso de que algo salga mal, evita que se modifique la base de datos
				$txn = $connection->startTransaction();
				$cont++;
				try{
					// guardamos la informacion del sitio
					$name = Html::escape($row[0]);
					$desc = Html::escape($row[1]);
					$sol = Html::escape($row[2]);
					$refer = Html::escape($row[3]);
					$link = Html::escape($row[4]);
					$resum = Html::escape($row[5]);
					$reco = Html::escape($row[6]);
					$vector = $row[7];
					$level = Html::escape($row[8]);

					// validamos que todos los datos hayan sido agregados en el archivo
					if(!empty($name) && !empty($desc) && !empty($sol) && !empty($refer) && !empty($link) && !empty($resum) && !empty($reco)	&& !empty($vector) && !empty($level)){
						// dividimos la entrada en score y nivel
						$nivel = explode(" ", $level);
						// quitamos los espacios en blanco y pasamos la cadena a mayusculas
						//$level = preg_replace('/\s+/', '',strtoupper($level));
						
						if(count($nivel) == 2){
							$score = $nivel[0]; // el puntaje de la vulnerabilidad
							$criti = $nivel[1]; // clasificacion de la vulnerabilidad
							// valores permitidos
							$levels = ['CRITICO', 'ALTO', 'MEDIO', 'BAJO'];

							// validamos que el nivel ingresado sea valido
							if(in_array($criti, $levels)){
								// almacena el mensaje que se muestra al final
								$msg_error = "";
								// validamos que el valor sea un flotante entre 0.0 y 10.0
								if(is_numeric($score) && ($score >= 0.0 &&  $score <= 10.0)){
									// parte 1 del mensaje de error
									$msg_vul = 'El puntaje para la vulnerabilidad';
									// validamos el score acorde a su nivel
									switch($criti){
									// CRITICO
									case $levels[0]:
										$rango = 'es entre 9.0 y 10.0';
										if(!($score <= 10.0 && $score >= 9.0)){
											$msg_error = "$msg_vul CRITICA $rango";
										}
										break;
									// ALTO
									case $levels[1]:
										$rango = 'es entre 7.0 y 8.9';
										if(!($score < 9 && $score >= 7)){
											$msg_error = "$msg_vul ALTA $rango";
										}
										break;
									// MEDIO
									case $levels[2]:
										$rango = 'es entre 4.0 y 6.9';
										if(!($score < 7 && $score >= 4)){
											$msg_error = "$msg_vul MEDIA $rango";
										}
										break;
									// BAJO
									case $levels[3]:
										$rango = 'es entre 0.1 y 3.9';
										if(!($score < 4 && $score > 0)){
											$msg_error = "$msg_vul BAJA $rango";
										}
										break;
									}
								} else {
									$msg_error = "Ingresa un valor menor o igual a 10 y mayor a 0";
								}
								
								if(empty($msg_error)){
									// validamos el vector
									$reg_ex = '/AV:[ANLP]\/AC:[HL]\/PR:[NLH]\/UI:[NR]\/S:[UC]\/C:[NLH]\/I:[NLH]\/A:[NLH](\/E:[UPFH])?(\/RL:[OTWU])?(\/RC:[URC])?(\/CR:[LMH])?(\/IR:[LMH])?(\/AR:[LMH])?(\/MAV:[NALP])?(\/MAC:[LH])?(\/MPR:[NLH])?(\/MUI:[NR])?(\/MS:[UC])?(\/MC:[NLH])?(\/MI:[NLH])?(\/MA:[NLH])?/';
									if(preg_match($reg_ex, $vector)){
										// validamos si existe el hallazgo 
										$select_hallazgo = $connection->select('hallazgos', 'h')
									        	->fields('h', array('id_hallazgo'))
											->condition("h.nombre_hallazgo_vulnerabilidad", "$name");
										$results = $select_hallazgo->execute()->fetchAll();
	
										// validamos la consulta tenga resultados
										if(empty($results)){
											// insert a la tabla de hallazgos
											$insert_hallazgos = $connection->insert('hallazgos')
										  	// Se agregan los campos a insertar que se obtienen de lo ingresado por el usuario
											->fields(array(
											  	'nombre_hallazgo_vulnerabilidad' => $name,
										  		'descripcion_hallazgo' => $desc,
										  		//'solucion_recomendacion_hallazgo' => $sol,
										  		'solucion_recomendacion_halazgo' => $sol,
										  		'referencias_hallazgo' => $refer,
											  	'recomendacion_general_hallazgo' => $reco,
										  		'nivel_cvss' => $level,
											  	'vector_cvss' => $vector,
											  	'enlace_cvss' => $link,
											  	'r_ejecutivo_hallazgo' => $resum,
										  	))
									  		// ejecutamos el query
									  		->execute();
			
											$messenger_service->addMessage(t("Hallazgo '$name' dado de alta, registro: $cont"));
										} else {
											$messenger_service->addWarning(t("El hallazgo '$name' ya existe"));
										}
									} else {
										$messenger_service->addError(t("Vector invalido, registro: $cont"));
									}
								} else {
									$messenger_service->addError(t("$msg_error, registro: $cont"));
								}
							} else {
								$messenger_service->addError(t("Ingrese el nivel de la forma: 5.5 MEDIO, registro: $cont"));
							}
						} else {
							$messenger_service->addError(t("Nivel de criticidad invalido, registro: $cont"));
						}
					} else {
						$messenger_service->addError(t("Valida la información ingresada, registro: $cont"));
					}
				} catch(Exception $e) {
					$txn->rollBack();
					\Drupal::logger('type')->error($e->getMessage());
				}
			}
		} else {
			// guardamos la informacion del sitio
			$name = Html::escape($form_state->getValue('name'));
			$desc = Html::escape($form_state->getValue('description'));
			$sol = Html::escape($form_state->getValue('solution'));
			$refer = Html::escape($form_state->getValue('references'));
			$link = Html::escape($form_state->getValue('cvss_link'));
			$resum = Html::escape($form_state->getValue('resume'));
			$reco = Html::escape($form_state->getValue('recomendation'));
			$vector = $form_state->getValue('cvss_vector');
			$level = $form_state->getValue('level');
			$level_score = $form_state->getValue('level_score');
			$full_level = "$level_score $level";

			// transaccion en caso de que algo salga mal al hacer cambios en la base de datos, evita que se modifique
			$txn = $connection->startTransaction();
			// hacemos un try catch por si no se ingresa un valor valido, validamos primero que se pueda insertar la ip
			try{

				// validamos si existe el hallazgo 
				$select_hallazgo = $connection->select('hallazgos', 'h')
					->fields('h', array('id_hallazgo'))
					->condition("h.nombre_hallazgo_vulnerabilidad", "$name");
				$results = $select_hallazgo->execute()->fetchAll();

				// validamos la consulta tenga resultados
				if(empty($results)){
					$insert_hallazgo = $connection->insert('hallazgos')
				  	// Se agregan los campos a insertar que se obtienen de lo ingresado por el usuario
					->fields(array(
					  	'nombre_hallazgo_vulnerabilidad' => $name,
				  		'descripcion_hallazgo' => $desc,
			  			'solucion_recomendacion_halazgo' => $sol,
			  			//'solucion_recomendacion_hallazgo' => $sol,
				  		'referencias_hallazgo' => $refer,
					  	'recomendacion_general_hallazgo' => $reco,
					  	'nivel_cvss' => $full_level,
					  	'vector_cvss' => $vector,
					  	'enlace_cvss' => $link,
					  	'r_ejecutivo_hallazgo' => $resum,
				  	))
		  			// ejecutamos el query
		  			->execute();
					 
					// mostramos el mensaje de que se hizo el insert
					$messenger_service->addMessage(t("Se dio de alta el hallazgo '$name'"));
				} else {
					$messenger_service->addWarning(t("El hallazgo '$name' ya existe"));
				}
			} catch(Exception $e) {
				$txn->rollBack();
				\Drupal::logger('type')->error($e->getMessage());
			}
		}

		// regresamos a la conexion default
		\Drupal\Core\Database\Database::setActiveConnection();
	}
}
?>
