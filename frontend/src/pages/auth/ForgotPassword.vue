<script setup lang="ts">
import { ref, onMounted, type HTMLAttributes } from 'vue';
import axios from 'axios';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import Turnstile from 'vue-turnstile';
import { useSettingsStore } from '@/stores/settings';

const settingsStore = useSettingsStore();
onMounted(async () => {
    await settingsStore.fetchSettings();
});

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
const { t: $t } = useI18n();
const router = useRouter();
const form = ref({
    email: '',
    turnstile_token: '',
});
const loading = ref(false);
const error = ref('');
const success = ref('');
const turnstileKey = settingsStore.settings?.turnstile_key_public as string;
const turnstileEnabled = settingsStore.settings?.turnstile_enabled as boolean;
function validateForm(): string | null {
    if (!form.value.email) {
        return $t('api_errors.MISSING_REQUIRED_FIELDS');
    }
    if (settingsStore.settings?.turnstile_enabled == 'true') {
        if (!form.value.turnstile_token) {
            return $t('api_errors.TURNSTILE_TOKEN_REQUIRED');
        }
    }
    if (typeof form.value.email !== 'string') {
        return $t('api_errors.INVALID_DATA_TYPE_EMAIL');
    }
    form.value.email = form.value.email.trim();
    if (form.value.email.length < 3) {
        return $t('api_errors.INVALID_DATA_LENGTH_MIN_EMAIL');
    }
    if (form.value.email.length > 255) {
        return $t('api_errors.INVALID_DATA_LENGTH_MAX_EMAIL');
    }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.value.email)) {
        return $t('api_errors.INVALID_EMAIL_ADDRESS');
    }
    return null;
}

function getErrorMessage(err: unknown): string {
    if (typeof err === 'object' && err !== null) {
        const e = err as { response?: { data?: { message?: string; error_code?: string } }; message?: string };
        const code = e.response?.data?.error_code;
        if (code) {
            switch (code) {
                case 'MISSING_REQUIRED_FIELDS':
                    return $t('api_errors.MISSING_REQUIRED_FIELDS');
                case 'INVALID_DATA_TYPE':
                    return $t('api_errors.INVALID_DATA_TYPE_EMAIL');
                case 'INVALID_DATA_LENGTH_MIN':
                    return $t('api_errors.INVALID_DATA_LENGTH_MIN_EMAIL');
                case 'INVALID_DATA_LENGTH_MAX':
                    return $t('api_errors.INVALID_DATA_LENGTH_MAX_EMAIL');
                case 'INVALID_EMAIL_ADDRESS':
                    return $t('api_errors.INVALID_EMAIL_ADDRESS');
                default:
                    break;
            }
        }
        return e.response?.data?.message || e.message || $t('api_errors.UNKNOWN_ERROR');
    }
    if (typeof err === 'string') return err;
    return $t('api_errors.UNKNOWN_ERROR');
}

async function onSubmit(e: Event) {
    e.preventDefault();
    error.value = '';
    success.value = '';
    const validationError = validateForm();
    if (validationError) {
        error.value = validationError;
        return;
    }
    loading.value = true;
    try {
        const payload = {
            email: form.value.email,
        };
        const res = await axios.put('/api/user/auth/forgot-password', payload, {
            headers: { 'Content-Type': 'application/json' },
        });
        if (res.data && res.data.success) {
            success.value = res.data.message || 'Registration successful.';
            router.push('/auth/login');
        } else {
            error.value = getErrorMessage(res.data);
        }
    } catch (err: unknown) {
        error.value = getErrorMessage(err);
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div :class="cn('flex flex-col gap-6', props.class)">
        <form @submit="onSubmit">
            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-4">
                    <div class="grid gap-3">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" :placeholder="'m@example.com'" required />
                    </div>
                    <Turnstile v-if="turnstileEnabled" v-model="form.turnstile_token" :site-key="turnstileKey" />
                    <Button type="submit" class="w-full" :disabled="loading">
                        <span v-if="loading">Send Reset Link...</span>
                        <span v-else>Send Reset Link</span>
                    </Button>
                    <div v-if="error" class="text-center text-sm text-red-500">{{ error }}</div>
                    <div v-if="success" class="text-center text-sm text-green-500">{{ success }}</div>
                    <div class="text-center text-sm">
                        Remember your password?
                        <router-link to="/auth/login" class="underline underline-offset-4"> Login </router-link>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
