<?php
/*
 * @file
 * Contains \Drupal\import_file\Form\ImportFileForm
 */
namespace Drupal\sitios_alta\Form;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\File;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\FileSystemInterface;

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
		// conexion a la base de datos secundaria
		\Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
		$connection = \Drupal\Core\Database\Database::getConnection();

		// Obtenemos la lista de dependencias
		$select = $connection->select('dependencias', 'r');
		$select->fields('r', array('id_dependencia', 'nombre_dependencia'));
		$select->orderBy('nombre_dependencia');
		$results = $select->execute();


		// regresamos a la conexion default
		\Drupal\Core\Database\Database::setActiveConnection();

		$options['vacio'] = "--Selecciona una dependencia--";
		foreach($results as $result){
			$options[$result->id_dependencia] = $result->nombre_dependencia;
		}
      
		$form_state->disableCache();
		$form['browser'] = array(
			'#type' => 'fieldset', 
			'#title' => t('En esta página puede dar de alta sitios.'), 
			'#collapsible' => TRUE, 
			'#description' => t("Llene los campos solicitados o cargue los datos desde un archivo CSV"),	
		); 
		$form['description'] = array(
			'#title' => t('Descripción del sitio.'),
			'#type' => 'textarea',
			'#size' => 60,
			'#description' => t("Ingresa la descripción del sitio"),
		);
		$form['ip'] = array(
			'#title' => t("Dirección IP del sitio."),
			'#type' => 'textfield',
			'#size' => 60,
			'#maxlength' => 128,
			'#description' => t("Ingresa una dirección IP para el sitio"),
		);
		$form['select'] = array(
			'#type' => 'select',
			'#title' => t("Dependencies"),
			'#options' => $options,
			'#description' => t('Selecciona la dependencia del sitio'),
		);
		$form['dependencia'] = array(
			'#title' => t("Nueva dependencia."),
			'#type' => 'textfield',
			'#size' => 60,
			'#maxlength' => 60,
			'#description' => t("Ingresa el nombre de la dependencia que desea agregar"),
		);
		$form['enlace'] = array(
			'#title' => t('Dirección URL.'),
			'#type' => 'textfield',
			'#size' => 60,
			'#maxlength' => 128,
			'#description' => t("Ingresa la url del sitio"),
		);
		$form['csv_file'] = array(
			'#type' => 'managed_file',
			'#title' => t('Archivo CSV.'),
			'#description' => t('Selecciona un archivo CSV'),
			'#upload_validators' => array(
				'file_validate_extensions' => array('csv'),
			),
			'#upload_location' => 'public://csv_files/sitios/alta_sitios/',
		);
		$form['alta'] = array(
			'#type' => 'submit',
			'#value' => t('Dar de alta'),
			'#name' => 'alta',
			'#button_type' => 'primary',
		);
		$form['alta_file'] = array(
			'#type' => 'submit',
			'#value' => t('Cargar archivo'),
			'#name' => 'alta_file',
			'#button_type' => 'primary',
		);
		return $form;
	}
	/*
	 *
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$button_clicked = $form_state->getTriggeringElement()['#name'];
		if($button_clicked == 'alta'){
			$ip = $form_state->getValue('ip');
		       
			// validacion de la descripcion
			if (strlen($form_state->getValue('description')) <= 0) {
				$form_state->setErrorByName('description', $this->t('Se debe agregar una descripcion del sitio'));
			}
      
			// validacion de la ip
			if (strlen($ip) <= 0) {
				$form_state->setErrorByName('ip', $this->t('Se debe ingresar una direccion ip'));
			} else if(!(filter_var($ip, FILTER_VALIDATE_IP) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))){
				$form_state->setErrorByName('ip', $this->t('Se debe ingresar una direccion ip valida'));
			}

			//$form_state->getValue('select');
							            //'depen']['dependencia'] = array(
			$depen_name = $form_state->getValue('dependencia');
		        $depen_select =  $form_state->getValue('select');
			$msg_1 = "Debe seleccionar una dependencia o ingresar el nombre de una nueva dependencia";
			$msg_2 = "Sólo puede seleccionar una dependencia o ingresar el nombre de una nueva dependencia";
			if (strlen($depen_name) <= 0 && $depen_select == "vacio") {
				$form_state->setErrorByName('select', $this->t("$msg_1"));
				$form_state->setErrorByName('dependencia', $this->t("$msg_1"));
			} else if (strlen($depen_name) > 0 && $depen_select != "vacio") {
				$form_state->setErrorByName('select', $this->t("$msg_2"));
				$form_state->setErrorByName('dependencia', $this->t("$msg_2"));
			}
				
			// validacion de la url
			if (strlen($form_state->getValue('enlace')) <= 0) {
				$form_state->setErrorByName('enlace', $this->t('Se debe ingresar una url'));
				//$form_state->setErrorByName('enlace', $this->t("$depen_select"));
			}

		} else if($button_clicked == 'alta_file'){
			// se valida que se haya cargado algun archivo(esto ya funciona)
			if ($form_state->getValue('csv_file') == NULL) {
				$form_state->setErrorByName('csv_file', $this->t('No se puede subir el archivo seleccionado'));
			}
		}
	}
      
	/*
	 * (@inheritdoc)
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		// conexion a la base de datos secundaria
		\Drupal\Core\Database\Database::setActiveConnection('drupaldb_segundo');
		$connection = \Drupal\Core\Database\Database::getConnection();
		
		$messenger_service = \Drupal::service('messenger');

		// identificamos el boton seleccionado
		if($form_state->getTriggeringElement()['#name'] == 'alta_file'){
			/* esto es para sacar lo del csv */
			// obtenemos el nombre del archivo      
			$file_csv = $form_state->getValue('csv_file');
			$file_content = \Drupal\file\Entity\File::load($file_csv[0]);

			// guardar el archivo de manera permanente en el servidor 
			$file_content->setPermanent();
			$file_content->save();
      
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
					// valores obtenidos del archivo
					$desc = $row[0];
					$url = $row[1];
					$ip = $row[2];
					$depen = $row[3];
					// validamos que tenga la cantidad necesaria valores por registro
					if(!empty($desc) && !empty($url) && !empty($ip) && !empty($depen)){
						// validamos que las direcciones ip sean validas
						if((filter_var($ip, FILTER_VALIDATE_IP) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))){

							// se consulta a la base de datos para ver si ya existe el sitio
							// se valida con la url     
							$select_url = $connection->select('sitios', 's')
						        ->fields('s', array('id_sitio'))
							->condition("s.url_sitio", "$url");
							$results = $select_url->execute()->fetchAll();

							// validamos que la consulta tenga resultados
							if(!empty($results)){
								// accedemos al id de la url
								foreach ($results as $record) {
									$id_sitio = $record->id_sitio;
								}
							} else {
								// insert del sitio a la tabla de sitios
								$id_sitio = $connection->insert('sitios')
								->fields(array(
									 'descripcion_sitio' => $desc,
									 'url_sitio' => $url,
								))
								->execute();
							}

					
							// se consulta a la base de datos para ver si ya existe la ip      
							$select_ip = $connection->select('dir_ip', 'd')
						        ->fields('d', array('id_ip'))
							->condition("d.dir_ip_sitios", "$ip");
							$results = $select_ip->execute()->fetchAll();

							// validamos la consulta tenga resultados
							if(!empty($results)){
								// accedemos al id de la ip
								foreach ($results as $record) {
									$id_ip = $record->id_ip;
								}
							} else {
								// insert a la tabla de ip
								$id_ip = $connection->insert('dir_ip')
				  				->fields(array(   
									'dir_ip_sitios' => $ip,
								)) ->execute();
							}

							// se consulta a la base de datos para obtener la dependencia
							$select_dependencia = $connection->select('dependencias', 'd')
						      	->fields('d', array('id_dependencia'))
							->condition("d.nombre_dependencia", "$depen");
							$results = $select_dependencia->execute()->fetchAll();
						
							if(!empty($results)){
								// accedemos al id de la dependencia
								foreach ($results as $record) {
									$id_dependencia = $record->id_dependencia;
								}
							} else {
								// insert a la tabla de ip
								$id_dependencia = $connection->insert('dependencias')
						   		->fields(array(   
									'nombre_dependencia' => $depen,
								)) ->execute();
							}
							
							// insert ip_sitios
							$query_ip_sitios = $connection->insert('ip_sitios')
							->fields(array(
								'id_ip' => $id_ip,
								'id_sitio' => $id_sitio,
							))
							->execute();
		
							// insert dependencias_sitios
							$query_ip_sitios = $connection->insert('dependencias_sitios')
						   	->fields(array(
								'id_dependencia' => $id_dependencia,
								'id_sitio' => $id_sitio,
							))
							->execute();
							$messenger_service->addMessage(t("Sitio dado de alta, registro: $cont"));

						} else {
							$messenger_service->addError(t("Ingreda una direccion ip valida, registro: $cont"));
						}
					} else {
						$messenger_service->addError(t("Valida la información ingresada, registro: $cont"));
					}
				} catch(Exception $e) {
					$txn->rollBack();
					\Drupal::logger('type')->error($e->getMessage());
				}
			}
			/* aca termina lo del archivo */
		} else {
			// guardamos la informacion del sitio
			$desc = Html::escape($form_state->getValue('description'));
			$url = Html::escape($form_state->getValue('enlace'));
			$id_dependencia = $form_state->getValue('select');
			$dependencia = $form_state->getValue('dependencia');
			$ip = $form_state->getValue('ip');

			// transaccion en caso de que algo salga mal al hacer cambios en la base de datos, evita que se modifique
			$txn = $connection->startTransaction();
								
			// hacemos un try catch por si no se ingresa un valor valido, validamos primero que se pueda insertar la ip
			try{
				// se consulta a la base de datos para ver si ya existe el sitio
				// se valida con la url     
				$select_url = $connection->select('sitios', 's')
				->fields('s', array('id_sitio'))
				->condition("s.url_sitio", "$url");
				$results = $select_url->execute()->fetchAll();

				// validamos que la consulta tenga resultados
				if(!empty($results)){
					// accedemos al id de la url
					foreach ($results as $record) {
						$id_sitio = $record->id_sitio;
					}
				// si el sitio no existe, se agrega a la base de datos
				} else {
					// insert del sitio a la tabla de sitios
					$id_sitio = $connection->insert('sitios')
					->fields(array(
						 'descripcion_sitio' => $desc,
						 'url_sitio' => $url,
					))
					->execute();
				}

				// se consulta a la base de datos para ver si ya existe la ip      
				$select_ip = $connection->select('dir_ip', 'd')
			        ->fields('d', array('id_ip'))
				->condition("d.dir_ip_sitios", "$ip");
				$results = $select_ip->execute()->fetchAll();

				// validamos la consulta tenga resultados
				if(!empty($results)){
					// accedemos al id de la ip
					foreach ($results as $record) {
						$id_ip = $record->id_ip;
					}
				} else {
					// insert a la tabla de ip
					$id_ip = $connection->insert('dir_ip')
	  				->fields(array(   
						'dir_ip_sitios' => $ip,
					)) ->execute();
				}

				if($id_dependencia == 'vacio'){
					// se consulta a la base de datos para obtener la dependencia
					$select_dependencia = $connection->select('dependencias', 'd')
				      	->fields('d', array('id_dependencia'))
					->condition("d.nombre_dependencia", "$dependencia");
					$results = $select_dependencia->execute()->fetchAll();
					
					if(!empty($results)){
						// accedemos al id de la dependencia
						foreach ($results as $record) {
							$id_dependencia = $record->id_dependencia;
						}
					} else {
						// insert a la tabla de dependencias
						$id_dependencia = $connection->insert('dependencias')
				   		->fields(array(   
							'nombre_dependencia' => $dependencia,
						)) ->execute();
					}
				}
						
				// insert ip_sitios
				$query_ip_sitios = $connection->insert('ip_sitios')
				->fields(array(
					'id_ip' => $id_ip,
					'id_sitio' => $id_sitio,
				))
				->execute();
	
				// insert dependencias_sitios
				$query_ip_sitios = $connection->insert('dependencias_sitios')
			   	->fields(array(
					'id_dependencia' => $id_dependencia,
					'id_sitio' => $id_sitio,
				))
				->execute();
				
				// mostramos el mensaje de que se hizo el insert
				$messenger_service->addMessage(t("Se dio de alta el sitio."));
			} catch(Exception $e) {
				$txn->rollBack();
				\Drupal::logger('type')->error($e->getMessage());
			}
		}

		// regresamos a la conexion default
		\Drupal\Core\Database\Database::setActiveConnection();
	}
}
