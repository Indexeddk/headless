    # Headless docs
Helper for the headless micro service platform

## Authentication

Use the key, secret and token in the Headless constructor to login to the REST api and then set the url if it's different that the default server.

    $headless = new Indexed\Headless\Request('ck_key', 'cs_secret', 'pt_token');
    $headless->setUrl('https://head01.webfamly.com/v1');
    
## Get list of products

When fetching the products, make a GET request to the endpoints /products

    $response = $headless->get('/products');
    
## Create a new product

Make a POST to /products to create a new product.

    $data = [
        'Brand new product',
    ];
    
    $response = $headless->post('/products', $data);
    
## Update existing product

Make a PATCH to /products with the item id that needs modification

    $patch = [
        'New product name',
    ];
    
    $response = $headless->patch('/products/'.$data->id, $patch);
    
## Get existing product by id

    $response = $headless->get('/products/'.$data->id);

## Handling errors

If a request fails it will return an error in the json object

    echo $response->error;
 