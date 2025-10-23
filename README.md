# My PHP Website

A modern, secure PHP website template ready for development and deployment to any web server.

## 📁 Project Structure

```
php website/
├── css/
│   └── style.css          # Main stylesheet with responsive design
├── js/
│   └── script.js          # JavaScript functionality
├── config/
│   └── config.php         # Database and site configuration
├── includes/
│   ├── header.php         # Reusable header component
│   └── footer.php         # Reusable footer component
├── index.php              # Homepage
├── about.php              # About page
├── contact.php            # Contact page with working form
├── .htaccess             # Apache configuration (security, clean URLs)
└── README.md             # This file
```

## 🚀 Getting Started

### Local Development

1. **Install a local server** (choose one):
   - [XAMPP](https://www.apachefriends.org/) (Windows, Mac, Linux)
   - [WAMP](https://www.wampserver.com/) (Windows)
   - [MAMP](https://www.mamp.info/) (Mac, Windows)
   - Use PHP's built-in server: `php -S localhost:8000`

2. **Place your files** in the web server directory:
   - XAMPP: `htdocs/your-project-name/`
   - WAMP: `www/your-project-name/`
   - MAMP: `htdocs/your-project-name/`

3. **Access your website**:
   - If using XAMPP/WAMP/MAMP: `http://localhost/your-project-name/`
   - If using built-in server: `http://localhost:8000/`

### Server Deployment

1. **Upload files** to your web server via FTP/SFTP
2. **Update configuration** in `config/config.php`:
   - Database credentials
   - Site URL
   - Email settings
3. **Set permissions** (if needed):
   - Make sure the web server can read all files
   - If you have uploads, create an `uploads/` folder with write permissions

## ⚙️ Configuration

### Database Setup

Edit `config/config.php` with your database details:

```php
define('DB_HOST', 'your_database_host');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');
```

### Site Settings

Update these constants in `config/config.php`:

```php
define('SITE_NAME', 'Your Website Name');
define('SITE_URL', 'https://yourwebsite.com');
define('SITE_EMAIL', 'your-email@yourwebsite.com');
```

### Security

1. **Change the encryption key** in `config/config.php`:
   ```php
   define('ENCRYPTION_KEY', 'generate-a-random-32-character-string');
   ```

2. **For production**, update `config/config.php`:
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```

3. **Enable HTTPS** in `.htaccess` (uncomment the HTTPS redirect lines)

## 🎨 Customization

### Adding New Pages

1. Create a new PHP file (e.g., `services.php`)
2. Use this template:

```php
<?php
// Include configuration if needed
// require_once 'config/config.php';

$pageTitle = "Services";
$pageDescription = "Our services description";
include 'includes/header.php';
?>

<main>
    <div class="container">
        <h2>Services</h2>
        <div class="content">
            <!-- Your content here -->
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
```

3. Add the new page to the navigation in `includes/header.php`

### Styling

- Edit `css/style.css` to customize the appearance
- The CSS includes responsive design for mobile devices
- Color scheme can be changed by updating the CSS variables

### JavaScript

- Add custom functionality in `js/script.js`
- The file includes basic form validation and smooth scrolling

## 📧 Contact Form

The contact form (`contact.php`) includes:
- Server-side validation
- XSS protection
- Success/error messages
- Responsive layout

To enable email sending:
1. Update SMTP settings in `config/config.php`
2. Install PHPMailer or use PHP's `mail()` function
3. Modify the form handling code in `contact.php`

## 🔒 Security Features

- **XSS Protection**: All user inputs are sanitized
- **CSRF Protection**: Functions included in config
- **Secure Headers**: Set via `.htaccess`
- **File Access Control**: Sensitive files protected
- **Session Security**: Secure session configuration
- **Clean URLs**: Remove `.php` extensions

## 📱 Responsive Design

The website is fully responsive and includes:
- Mobile-first CSS approach
- Flexible grid layout
- Touch-friendly navigation
- Optimized images and content

## 🛠️ Maintenance

### Regular Updates

1. Keep PHP updated on your server
2. Regularly update any third-party libraries
3. Monitor server logs for errors
4. Backup your database and files regularly

### Performance Optimization

- The `.htaccess` file includes compression and caching rules
- Optimize images before uploading
- Consider using a CDN for static assets
- Monitor page load times

## 📝 Next Steps

1. **Customize Content**: Update all placeholder text and images
2. **Add Features**: Implement additional functionality as needed
3. **SEO Optimization**: Add meta tags, sitemaps, and analytics
4. **Testing**: Test on different devices and browsers
5. **Backup Strategy**: Set up regular backups
6. **Monitoring**: Implement error logging and uptime monitoring

## 🆘 Troubleshooting

### Common Issues

1. **"Page not found" errors**: Check file permissions and `.htaccess` rules
2. **CSS/JS not loading**: Verify file paths and server permissions
3. **Database connection errors**: Check credentials in `config/config.php`
4. **Form not working**: Ensure POST method is enabled on your server

### Getting Help

- Check server error logs
- Verify PHP version compatibility (requires PHP 7.0+)
- Ensure all required PHP extensions are installed
- Test with a simple `<?php phpinfo(); ?>` file

## 📄 License

This project is open source and available under the MIT License.

## 🤝 Contributing

Feel free to submit issues and enhancement requests!

---

Happy coding! 🚀 