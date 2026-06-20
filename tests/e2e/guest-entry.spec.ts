import { expect, test } from '@playwright/test';

test('guest can reach the application entry point', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveURL(/\/(login|dashboard)(\?.*)?$/);
    await expect(page.locator('body')).toBeVisible();
});
