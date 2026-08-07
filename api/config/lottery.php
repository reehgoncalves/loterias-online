<?php

return [
    'risk' => [
        'max_bet_cents' => (int) env('RISK_MAX_BET_CENTS', 100000),
    ],
    // Fonte de referência: páginas oficiais do Portal Loterias CAIXA.
    // A matriz é versionada no cadastro para que preço e quantidade nunca
    // dependam de dados enviados pelo navegador.
    'official_rules' => [
        'mega-sena' => [
            'min_numbers' => 6, 'max_numbers' => 20, 'range_min' => 1, 'range_max' => 60,
            'price_table' => [6=>600,7=>4200,8=>16800,9=>50400,10=>126000,11=>277200,12=>554400,13=>1029600,14=>1801800,15=>3003000,16=>4804800,17=>7425600,18=>11138400,19=>16279200,20=>23256000],
            'bolao' => ['min_total_cents'=>1800,'min_share_cents'=>700,'min_shares'=>2,'max_shares'=>100,'max_games'=>10,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Mega-Sena.aspx',
        ],
        'lotofacil' => [
            'min_numbers' => 15, 'max_numbers' => 20, 'range_min' => 1, 'range_max' => 25,
            'price_table' => [15=>350,16=>5600,17=>47600,18=>285600,19=>1356600,20=>5426400],
            'bolao' => ['min_total_cents'=>1400,'min_share_cents'=>450,'min_shares'=>2,'max_shares'=>100,'max_games'=>10,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Lotofacil.aspx',
        ],
        'quina' => [
            'min_numbers' => 5, 'max_numbers' => 15, 'range_min' => 1, 'range_max' => 80,
            'price_table' => [5=>300,6=>1800,7=>6300,8=>16800,9=>37800,10=>75600,11=>138600,12=>237600,13=>386100,14=>600600,15=>900900],
            'bolao' => ['min_total_cents'=>1500,'min_share_cents'=>400,'min_shares'=>2,'max_shares'=>50,'max_games'=>10,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Quina.aspx',
        ],
        'timemania' => [
            'min_numbers' => 10, 'max_numbers' => 10, 'range_min' => 1, 'range_max' => 80,
            'price_table' => [10=>350], 'special_type' => 'team',
            'bolao' => ['min_total_cents'=>700,'min_share_cents'=>350,'min_shares'=>2,'max_shares'=>15,'max_games'=>15,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Timemania.aspx',
        ],
        'dia-de-sorte' => [
            'min_numbers' => 7, 'max_numbers' => 15, 'range_min' => 1, 'range_max' => 31,
            'price_table' => [7=>250,8=>2000,9=>9000,10=>30000,11=>82500,12=>198000,13=>429000,14=>858000,15=>1608750],
            'special_type' => 'month',
            'bolao' => ['min_total_cents'=>1200,'min_share_cents'=>300,'min_shares'=>2,'max_shares'=>100,'max_games'=>10,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Dia-de-Sorte.aspx',
        ],
        'dupla-sena' => [
            'min_numbers' => 6, 'max_numbers' => 15, 'range_min' => 1, 'range_max' => 50,
            'price_table' => [6=>300,7=>2100,8=>8400,9=>25200,10=>63000,11=>138600,12=>277200,13=>514800,14=>900900,15=>1501500],
            'bolao' => ['min_total_cents'=>1000,'min_share_cents'=>300,'min_shares'=>2,'max_shares'=>50,'max_games'=>10,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Dupla-Sena.aspx',
        ],
        'lotomania' => [
            'min_numbers' => 50, 'max_numbers' => 50, 'range_min' => 0, 'range_max' => 99,
            'price_table' => [50=>300], 'special_type' => 'mirror_optional',
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Lotomania.aspx',
        ],
        'super-sete' => [
            'min_numbers' => 7, 'max_numbers' => 21, 'range_min' => 0, 'range_max' => 9,
            'price_table' => [7=>300,8=>600,9=>1200,10=>2400,11=>4800,12=>9600,13=>19200,14=>38400,15=>57600,16=>86400,17=>129600,18=>194400,19=>291600,20=>437400,21=>656100],
            'special_type' => 'columns', 'columns' => 7,
            'bolao' => ['min_total_cents'=>1000,'min_share_cents'=>300,'min_shares'=>2,'max_shares'=>100,'max_games'=>10,'same_number_count'=>true],
            'source_url' => 'https://loterias.caixa.gov.br/Paginas/Super-sete.aspx',
        ],
    ],
];
