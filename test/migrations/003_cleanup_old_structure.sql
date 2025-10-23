-- Migration: Clean up old course-faculty structure (OPTIONAL - Run after application update)
-- WARNING: Only run this after updating the application to use the new materii structure!

USE [Grafica_UTCN]
GO

-- This script should only be run AFTER the application has been updated to use materii
-- and you have verified that everything works correctly with the new structure

-- Step 1: Verification check before cleanup
IF NOT EXISTS (SELECT 1 FROM [dbo].[course_materii])
BEGIN
    PRINT 'ERROR: No course-materii relationships found!'
    PRINT 'Please ensure the migration was completed successfully before running cleanup.'
    RETURN
END

-- Step 2: Show what will be removed
PRINT 'The following course-faculty relationships will be removed:'
SELECT 
    c.title as course_title,
    f.name as faculty_name,
    cf.assigned_at
FROM [dbo].[course_faculties] cf
INNER JOIN [dbo].[courses] c ON cf.course_id = c.id
INNER JOIN [dbo].[faculty] f ON cf.faculty_id = f.id
ORDER BY f.name, c.title

-- Step 3: Count relationships to be preserved vs removed
SELECT 
    'Old course-faculty relationships' as relationship_type,
    COUNT(*) as count
FROM [dbo].[course_faculties]

UNION ALL

SELECT 
    'New course-materii relationships' as relationship_type,
    COUNT(*) as count
FROM [dbo].[course_materii]

-- Step 4: Optional cleanup (commented out for safety)
-- Uncomment the following lines ONLY after verifying the application works with materii

/*
-- Drop the migration status view
DROP VIEW IF EXISTS [dbo].[v_migration_status]
GO

-- Drop the old course_faculties table
DROP TABLE [dbo].[course_faculties]
GO

PRINT 'Cleanup completed successfully!'
PRINT 'Old course_faculties table has been removed.'
*/

-- For now, just print instructions
PRINT ''
PRINT '=== CLEANUP INSTRUCTIONS ==='
PRINT 'This script is currently in SAFE MODE.'
PRINT 'To complete the cleanup after application update:'
PRINT '1. Verify the new materii system works correctly'
PRINT '2. Uncomment the cleanup section in this script'
PRINT '3. Run the script again to remove old tables'
PRINT ''
PRINT 'Tables that will be removed in cleanup:'
PRINT '- course_faculties (old many-to-many table)'
PRINT '- v_migration_status (migration helper view)'
