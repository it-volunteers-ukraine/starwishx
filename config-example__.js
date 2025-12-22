const config = {
    "localhost": {
        "proxy": "http://starwishx.local",
        "tunnel": false,
        // "open": true,  // отероет в браузере http://localhost:3000
        "open": "external", // отероет в браузере http://starwishx.local:3000
        "host": "starwishx.local",
        "port": 3000,
        "https": false
    },
    "theme": {
        "name": "StarwishX",
        "uri": "_themeuri",
        "domain": "_themedomain",
        "prefix": "_themeprefix",
        "author": "it-volunteers",
        "authoruri": "https://it-volunteers.com/",
        "version": "_themeversion",
        "description": "Starter WordPress theme"
    }
}

export default config;