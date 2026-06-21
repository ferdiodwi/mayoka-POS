<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '@/composables/useAuth';
import { useToast } from 'primevue/usetoast';

const router = useRouter();
const toast = useToast();
const { login, loading } = useAuth();

const username = ref('');
const password = ref('');
const errorMsg = ref('');

async function handleLogin() {
    errorMsg.value = '';

    if (!username.value || !password.value) {
        errorMsg.value = 'Username dan password harus diisi.';
        return;
    }

    try {
        const data = await login(username.value, password.value);
        toast.add({
            severity: 'success',
            summary: 'Berhasil',
            detail: `Selamat datang, ${data.user.name}!`,
            life: 2000,
        });

        // Redirect based on role
        if (data.user.role === 'owner') {
            router.push('/');
        } else {
            router.push('/pos');
        }
    } catch (err) {
        errorMsg.value = err.message;
        toast.add({
            severity: 'error',
            summary: 'Login Gagal',
            detail: err.message,
            life: 4000,
        });
    }
}
</script>

<template>
    <Toast />
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden">
        <div class="flex flex-col items-center justify-center">
            <div
                style="
                    border-radius: 56px;
                    padding: 0.3rem;
                    background: linear-gradient(180deg, var(--primary-color) 10%, rgba(33, 150, 243, 0) 30%);
                "
            >
                <div class="w-full bg-surface-0 dark:bg-surface-900 py-20 px-8 sm:px-20" style="border-radius: 53px">
                    <div class="text-center mb-8">
                        <div class="text-surface-900 dark:text-surface-0 text-3xl font-medium mb-2">
                            Mayoka POS
                        </div>
                        <span class="text-muted-color font-medium">Aplikasi Kasir Toko Fotokopi & ATK</span>
                    </div>

                    <div>
                        <Message v-if="errorMsg" severity="error" :closable="false" class="mb-4">{{ errorMsg }}</Message>

                        <label for="username" class="block text-surface-900 dark:text-surface-0 text-xl font-medium mb-2">Username</label>
                        <InputText
                            id="username"
                            v-model="username"
                            type="text"
                            placeholder="Masukkan username"
                            class="w-full md:w-[30rem] mb-8"
                            :invalid="!!errorMsg"
                            @keyup.enter="handleLogin"
                            @input="errorMsg = ''"
                        />

                        <label for="password" class="block text-surface-900 dark:text-surface-0 font-medium text-xl mb-2">Password</label>
                        <Password
                            inputId="password"
                            v-model="password"
                            placeholder="Masukkan password"
                            :toggleMask="true"
                            class="mb-8"
                            fluid
                            :invalid="!!errorMsg"
                            :feedback="false"
                            @keyup.enter="handleLogin"
                            @input="errorMsg = ''"
                        />

                        <Button
                            label="Masuk"
                            icon="pi pi-sign-in"
                            class="w-full"
                            :loading="loading"
                            @click="handleLogin"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.pi-eye {
    transform: scale(1.6);
    margin-right: 1rem;
}

.pi-eye-slash {
    transform: scale(1.6);
    margin-right: 1rem;
}
</style>
