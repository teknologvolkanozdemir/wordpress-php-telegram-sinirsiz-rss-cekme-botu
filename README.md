# WordPress RSS Bot

WordPress eklentisi, eklediğiniz RSS kaynaklarındaki yeni yazıları otomatik olarak WordPress yazılarına aktarır. Her kaynak eklenirken isteğe bağlı bir kategori seçilebilir; kategori seçilmezse WordPress'in varsayılan kategorisi kullanılır.

## Kurulum

1. `wordpress-rss-bot.php` dosyasını `wp-content/plugins/wordpress-rss-bot/` klasörüne yükleyin.
2. WordPress yönetim panelinde **Eklentiler** sayfasından **WordPress RSS Bot** eklentisini etkinleştirin.
3. **Araçlar → RSS Bot** sayfasından RSS URL'si ve isteğe bağlı kategoriyi seçip kaynakları ekleyin.

Ekleyebileceğiniz kaynak sayısında bir sınır yoktur. Bot, RSS kaynaklarını 50 saniyelik WordPress zamanlanmış göreviyle kontrol eder ve her kaynağın en son 20 öğesini yinelenen kayıt oluşturmadan yayımlar.

Zamanlanmış görevler WordPress'in WP-Cron mekanizmasını kullanır; görevler site trafiği olduğunda çalıştırılır. Gerçek zamanlı 50 saniyelik çalışmayı garanti etmek için sunucunuzda WordPress cron'unu düzenli aralıklarla tetikleyen bir sistem cron görevi yapılandırın.
