facebook marketingapi playground
================================
just for some tests with facebook marketing api

howto deploy
============

**App:**
```
scp -r App username@server:/path/to/playgrounds/facebook/
```

**vendor:**
```
scp -r vendor username@server:/path/to/playgrounds/facebook/
```

**index/config/fb./:**  
(only if required - beware of overwriting remote modified files)
```
scp config.php index.php fb.* username@server:/path/to/playgrounds/facebook/
```
