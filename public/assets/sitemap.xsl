<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>
  <xsl:template match="/">
    <html>
      <head>
        <title>RetroBB Sitemap</title>
        <style>
          body{font-family:Verdana,Tahoma,Arial,sans-serif;background:#dfe3ee;color:#222;margin:0}
          .wrap{max-width:800px;margin:16px auto;background:#fff;border:1px solid #7a96c2;padding:16px}
          h1{background:linear-gradient(180deg,#7db3e8,#3A6EA5);color:#fff;padding:8px 12px;margin:0 0 12px;font-size:16px}
          li{margin:4px 0} a{color:#1a3f7a}
          .note{color:#667;font-size:12px}
        </style>
      </head>
      <body><div class="wrap">
        <h1>RetroBB Sitemap</h1>
        <p class="note">This XML sitemap is for search engines — <a href="/">back to the board index</a>.</p>
        <ul>
          <xsl:for-each select="s:urlset/s:url">
            <li><a href="{s:loc}"><xsl:value-of select="s:loc"/></a></li>
          </xsl:for-each>
        </ul>
      </div></body>
    </html>
  </xsl:template>
</xsl:stylesheet>
