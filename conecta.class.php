<?php
/**
* Classe para facilitar alterações e acesso ao Banco via PDO
*
* @name Conecta
* @author Daniel da Silva Bispo
* @webpage http://about.me/daniel.bispo
* @twitter http://twitter.com/webmaster_cn
* @facebook http://www.facebook.com/TMW.Tecnology
* @link https://github.com/szagot/Conecta
* @version 7.3
* @since 01/12/2011
* @lastUpdate 15/10/2014
*/
class Conecta
{
	/**
	* Nome do Banco de Dados Principal.
	* @access private
	* @var string
	*/
	private $db = 'Banco de Dados'; 

	/**
	* Variáveis de Banco de Dados. Não há necessidade de informar caso se trabalhe localmente. Ver método construtor.
	* @access private
	* @var string
	*/
	private 
        $host 	= 'localhost',
	    $user 	= 'root',
	    $pass 	= ''; 
    
    /**
    * Opções de conexão
	* @access private
	* @var array
    */
    private $pdoOptions = array(
            // Modo de tratamento de erro por Exceções
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Cache de conexão
            PDO::ATTR_PERSISTENT => true
        );
    
	/**
	* Conexão com o PDO
	* @access protected
	* @var object
	*/
	protected $pdoConecta; 				

	/**
	* Variável para execução da Query
	* @access protected
	*/
	protected $sqlQuery;

	/**
	* Variáveis a serem setadas com setVars()
	* @access protected
	*/
	protected 
		// Tipo da Query. Valores aceitos: SELECT - INSERT - REPLACE - UPDATE - DELETE.
		$type,
		// Colunas a serem selecionadas. Apenas para $this->type = "SELECT", senão será ignorado
		$selQual, 	
		// Tabela ou tabelas a acessar.
		$table, 	
		// Junção de tabelas com JOIN
		$qJoin, 	
		// Ordenação da Query. Ex.: $pdo->setaVars( array( 'order' => 'DATA DESC, NOME ASC' ) );
		$order, 	
		// Condições da Query. Ex.: $pdo->setaVars( array( 'where' => 'id = 30' ) );
		$where, 	
		// Limitação da query. Desnecessária se $this->oneRow = true. Ex.: $pdo->setaVars( array( 'limit' => '0,10' ) );
		$limit, 	
		// Indicador de retorno. Quando "true", $this->limit será = "0,1" e o retorno será um objeto com os campos da query
		$oneRow, 	
		// Campos da Query. Usar * quando valor contiver cód. HTML. Ex.: $pdo->setaVars( array( 'campos' => array( 'descricao*' = '<p></p>' ) ) );
		$campos; 	
			
	/**
	* Variáveis de retorno informativo após $this->execute(), sem valor atuante para a classe
	* @access public
	*/
	public 	
		// Query executada.
		$sql, 		
		// Erros de execução da Query, se houverem.
		$erro,	 	
		// Número de registros afetados pela Query.
		$numRegs, 	
		// Último registro inserido pela Query, em caso de INSERT ou REPLACE.
		$ultimoReg;	
	
	/**
	* Método construtor
	* @access public
	* @param string $qualTabela Nome da Tabela a ser Acessada
	*/
	public function __construct( $qualTabela = '' ) 
	{ 
		// Verifica se não está trabalhando localmente 
		$tipo_conexao = $_SERVER['HTTP_HOST'];
		if ( ( $tipo_conexao == 'localhost' ) || ( $tipo_conexao == '127.0.0.1' ) ) {
			
			$this->host	= 'localhost';
			$this->user	= 'root';
			$this->pass	= ''; 
			
		}

		// Diretiva de acesso com MySQL
		$acesso = 'mysql:host=' . $this->host . ';dbname=' . $this->db; 

		// Apenas os erros fatais serão reportados
		error_reporting( E_ERROR );
		try 
		{
			// Conectando
			$this->pdoConecta = new PDO( $acesso, $this->user, $this->pass, $this->pdoOptions ) ;
			$this->zeraVars( $qualTabela );
		} 
		catch ( PDOException $errorConecta ) 
		{
			echo $this->erro = '<strong>Erro ao conectar com o PDO: </strong>' . $errorConecta->getMessage();
			exit;
		}
	}
	
	/**
	* Modifica o banco de dados, reiniciando conexão
	* @access public
	* @param string $db 		Nome do Banco de Dados
	* @param string $host 		Host de Acssso
	* @param string $user 		Usuário do BD
	* @param string $pass 		Senha do BD
	* @param string $qualTabela Nome da Tabela a ser Acessada
	*/
	public function restartAccess( $db, $host, $user, $pass, $qualTabela = '' ) 
	{ 
		if ( is_string( $db ) && is_string( $host ) && is_string( $user ) && is_string( $pass ) ) {	

			// Modificando dados
			$tipo_conexao = $_SERVER['HTTP_HOST'];
			if ( ( $tipo_conexao == 'localhost' ) || ( $tipo_conexao == '127.0.0.1' ) ) {
				$this->host	= 'localhost';
				$this->user	= 'root';
				$this->pass	= ''; 
				
			} else {
				$this->host = $host;
				$this->user = $user;
				$this->pass = $pass;
			}
			
			$this->db = $db;
		
			// Diretiva de acesso com MySQL
			$acesso = 'mysql:host=' . $this->host . ';dbname=' . $this->db; 
	
			try 
			{
				// Conectando
				$this->pdoConecta = new PDO( $acesso, $this->user, $this->pass, $this->pdoOptions ) ;
				$this->zeraVars( $qualTabela );
			} 
			catch ( PDOException $errorConecta ) 
			{
				echo $this->erro = '<strong>Erro ao reconectar com o PDO: </strong>' . $errorConecta->getMessage();
				exit;
			}
		}
	}
	
	/**
	* Retorna as variáveis ao seu padrão.
	* @access public
	* @param string $qualTabela Nome da Tabela a ser modificada
	*/
	public function zeraVars( $qualTabela = '' ) 
	{
		// Se $qualTabela não for declarada, ele mantém a mesma tabela já acessada.
		$qualTabela = ( $qualTabela != '' && is_string( $qualTabela ) ) ? $qualTabela : $this->table; 
		// Seta variáveis para o padrão
		$this->setVars( array(
			'type' => 'SELECT',
			'table' => $qualTabela,
			'selQual' => '*',
			'qJoin' => '',
			'order' => '', 
			'where' => '', 
			'limit' => '', 
			'oneRow' => false,
			'campos' => ''
		) );
		
	}
	
	/**
	* Seta as variáveis. Se uma variável contiver um valor inválido, ele receberá o seu padrão
	* @access public
	* @param array $vars Variáveis a serem setadas
	*/
	public function setVars( $vars = array() )
	{
		if ( count( $vars ) > 0 ) {
			
			foreach( $vars as $var => $value )				
			
				switch( $var ) {
					
					case 'type':
						if ( preg_match( '/(select|update|delete|insert|replace)/i', $value ) )
							$this->type = strtoupper( $value );
						else
							$this->type = 'SELECT';
						break;
					
					case 'table':
						if ( $value != '' && is_string( $value ) )
							$this->table = $value;
						else
							return false;
						break;
					
					case 'selQual':
						if ( is_string( $value ) )
							$this->selQual = $value;
						else
							$this->selQual = '*';
						break;
					
					case 'qJoin':
						if ( is_string( $value ) )
							$this->qJoin = $value;
						else
							$this->qJoin = '';
						break;
					
					case 'order':
						if ( is_string( $value ) )
							$this->order = $value;
						else
							$this->order = '';
						break;
					
					case 'where':
						if ( is_string( $value ) )
							$this->where = $value;
						else
							$this->where = '';
						break;
					
					case 'limit':
						if ( is_string( $value ) )
							$this->limit = $value;
						else
							$this->limit = '';
						break;

					case 'oneRow':
						if ( $value )
							$this->oneRow = true;
						else
							$this->oneRow = false;
						
						break;
					
					case 'campos':
						if ( ( is_array( $value ) || is_object( $value ) ) && count( $value ) > 0 )
							$this->campos = $value;
						else
							$this->campos = '';
							
						break;
					
				}
			
			return true;
			
		} else
		
			return false; 
			
	}
	
	/**
	* Executa a Query conforme configuração dos elementos
	* @access public
	* @param string  $sql       Código MySQL para quando se for executar outras operações. Se declarado, demais variáveis serão ignoradas.
	* @param boolean $oneRow    Quando true, retorna apenas 1 linha da query, em forma de objeto. Quando false, retorna uma array com todas as linhas da query em forma de objeto
	*/
	public function execute( $sql = '', $oneRow = false ) 
	{
		if ( $this->type != '' && $this->table != '' && $sql == '' ) {
			
			$this->erro = '';
			
			try	
			{
				// Inicia SQL com o tipo
				$this->sqlQuery = $this->type; 
				
				switch ( $this->type ) {
					case 'INSERT':
					
					case 'REPLACE':	
						$this->sqlQuery .= ' INTO ' . $this->table . ' (';
						// Verificador de primeira passada
						$x = true;
						foreach ( $this->campos as $chave => $valor )
							if ( $x ) {
								// Acrescenta CAMPO
								$this->sqlQuery .= preg_replace( "/\*/", "", $chave );
								$x = false;
							} else
								$this->sqlQuery .= ", " . preg_replace( "/\*/", "", $chave );
						$this->sqlQuery .= ") VALUES (";
						$x = true;
						foreach ( $this->campos as $chave => $valor )
							if ( $x ) {
								$this->sqlQuery .= ":" . preg_replace( "/\*/", "", $chave );
								$x = false;
							} else
								$this->sqlQuery .= ", :" . preg_replace( "/\*/", "", $chave );
						$this->sqlQuery .= ") ";
						break;
						
					case 'UPDATE':	
						$this->sqlQuery .= " " . $this->table." SET ";
						// Verificador de primeira passada
						$x = true; 
						foreach ( $this->campos as $chave => $valor )
							if ( $x ) {
								// Acrescenta CAMPO = :CAMPO
								$this->sqlQuery .= preg_replace( "/\*/", "", $chave ) . " = :" . preg_replace( "/\*/", "", $chave ) . " "; 
								$x = false;
							} else
								$this->sqlQuery .= ", " . preg_replace( "/\*/", "", $chave ) . " = :" . preg_replace( "/\*/", "", $chave ) . " "; 
						break;
						
					case 'DELETE':	
						$this->sqlQuery .= " FROM " . $this->table." ";
						break;
						
					default: 
						// "SELECT"
						$this->sqlQuery .= " " . ( ( $this->selQual != '' ) ? $this->selQual : "*" ) . " FROM " . $this->table . " " . $this->qJoin . " ";
				}
				
				// Conferindo oneRow
				if ( $this->oneRow && $this->type == "SELECT" ) $this->limit = "0,1";
				
				// Conferindo where
				if ( $this->where != '' ) 
					$this->sqlQuery .= "WHERE " . $this->where . " ";
					
				//Conferindo order
				if ( $this->order != '' ) 
					$this->sqlQuery .= "ORDER BY " . $this->order . " ";
					
				// Limitação apenas ocorre se não houver paginação configurada
				if ( $this->limit != '' ) 
					$this->sqlQuery .= "LIMIT " . $this->limit . " ";
				
				// Preparando Query
                $this->pdoConecta->beginTransaction();
				$query = $this->pdoConecta->prepare( $this->sqlQuery );

				// Conferindo campos com bindValue
				foreach ( $this->campos as $chave => $valor ) 
					// Valor Nulo
					if ( $valor == NULL )
						$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_NULL );
					// Valor Integer
					else if ( is_integer( $valor ) ) 
						$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_INT );
					// Outros Valores
					else 
						// Analisa se está previsto valores HTML (* no campo)
						if ( preg_match( '/\*/', $chave ) ) 
							$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_STR );
						// Se não passou em nenhum dos testes anteriores, elimina qq/ traço de cóidigo no valor
						else
							$query->bindValue( ":" . $chave, strip_tags( trim( $valor ) ), PDO::PARAM_STR );

				// Corrigindo Query para Análise
				$this->sql = $this->sqlQuery;
				foreach ( $this->campos as $chave => $valor ) {
					if ( preg_match( '/\*/', $chave ) ) {
						$valor = htmlentities( utf8_decode( $valor ) );
						$chave = preg_replace( '/\*/', '', $chave );
					}
					$regex = "/:$chave/";
					$subst = "'$valor'";
					$this->sql = preg_replace( $regex, $subst, $this->sql );
				}

				//Executando Query
				$query->execute();
                
                // Confirmando a transação
				$this->pdoConecta->commit();
                
				// Numero de registros afetados
				$this->numRegs = $query->rowCount();
				
				if ( $this->type == "INSERT" || $this->type == "REPLACE" )
					// Último registro inserido
					$this->ultimoReg = $this->pdoConecta->lastInsertId();

				if ( $this->type == "SELECT" ) {
					// Para alguns tipos de BD, rowCount não funciona com SELECT
					if ( $this->numRegs == 0 ) $this->numRegs = count( $query->fetchAll() ); 

					//Retorno diferenciado por tipo de consulta
					if ( $this->oneRow )
						// Retorna apenas um único registro no formato de objeto
						return $query->fetchObject(); 
					else
						// Retorna um array com todos os registros da consulta, cada linha sendo um objeto
						return $query->fetchAll( PDO::FETCH_OBJ ); 

				} else 
					return true;
	
			} 
			catch ( PDOException $error_query ) 
			{
				// Se for alguma alteração, desfaz a última ação não comitada
                if ( $this->type != 'SELECT' )
                    $this->pdoConecta->rollback();
                
                $this->erro = 'Erro obtido em ' . date( 'd/m/Y H:i:s' ) . '(' . $error_query->getCode() . '): ' . $error_query->getMessage()
							. ' | SQL -> ' . $this->sql;
				return false;
			}
			
		} else if ( $sql != '' ) {
			
			$this->erro = '';
			
			// Execução de um código SQL direto
			try
			{
				$this->pdoConecta->beginTransaction();
                
                $query = $this->pdoConecta->query( $sql );
                
                $this->pdoConecta->commit();

				// Numero de registros afetados
				$this->numRegs = $query->rowCount();

				if ( preg_match( '/^(insert|replace)/i', $sql ) )
					// Último registro inserido
					$this->ultimoReg = $this->pdoConecta->lastInsertId();
				
				//Retorno diferenciado por tipo de consulta
				if ( $oneRow )
					// Retorna apenas um único registro no formato de objeto
					return $query->fetchObject(); 
				else
					// Retorna um array com todos os registros da consulta, cada linha sendo um objeto
					return $query->fetchAll( PDO::FETCH_OBJ ); 

			} 
			catch ( PDOException $error_query ) 
			{
				// Se for alguma alteração, desfaz a última ação não comitada
                if ( preg_match( '/^(insert|replace|update|delete)/i', $sql ) )
                    $this->pdoConecta->rollback();

				$this->erro = 'Erro obtido em ' . date( 'd/m/Y H:i:s' ) . '(' . $error_query->getCode() . '): ' . $error_query->getMessage()
							. ' | SQL -> ' . $sql;
				return false;
			}	
					
		} else {
			
			$this->erro = 'Parâmetros insuficientes';
			return false;
		
		}
	}
	
}
?>
