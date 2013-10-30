#include <stdlib.h>
#include <pthread.h>
#include <semaphore.h>

#define MAX_ACTIVITY_SIZE 1024

struct activity_queue{
	sem_t* activities[MAX_ACTIVITY_SIZE]; //what if we used a more sophisticated data structure
	int n; //Number of activities in queue
	int head;
	int tail;

};

struct threadarg{
	struct activity_queue* queue;
	sem_t* sem;
	sem_t* master_sem;
	void* args;
};

void enqueue(struct activity_queue* q, sem_t* new_sem){
	q->activities[q->tail] = new_sem;
	q->tail = (q->tail + 1) % MAX_ACTIVITY_SIZE;
	q->n++;
}

void yield(sem_t* my_sem, sem_t* master_sem, struct activity_queue* q){
	//enqueue self?? inline this?
	enqueue(q, my_sem);
	sem_post(master_sem);
	sem_wait(my_sem);
}


//my_sem is the semaphore of the activity we called this from
void add_activity(struct activity_queue* q, sem_t* master_sem, sem_t* my_sem, void* func_pointer){
	//make a new thread
	//immediately wait in new thread
	pthread_t* new_activity;
	sem_t* new_sem = malloc(sizeof(sem_t));
	sem_init(new_sem, 1, 1);
    struct threadarg arg;
	arg.queue = q;
	arg.sem = new_sem;
	arg.master_sem = master_sem;
	enqueue(q, new_sem);
	pthread_create(new_activity, NULL, func_pointer, (void*) &arg);
	sem_wait(my_sem);
}

void my_activity_function(struct threadarg* args){
	sem_t* my_sem = args->sem;
	sem_t* master_sem = args->master_sem;
	struct activity_queue* q = args->queue;
	for(int i = 0; i < 1000; i++){
		i++;
	}
	yield( my_sem, master_sem, q);
	sem_post(master_sem); //Need to do this at end of function...?
}

void master_thread_func(sem_t* master_sem, struct activity_queue q){
    for(int i = 0; i < 100; i++){
        add_activity(&q, master_sem, master_sem, (void*) my_activity_function);
    }
	while(1){
		sem_wait(master_sem);
		if(q.n == 0){
			break;
		}
		sem_t* cur = q.activities[q.head];
		q.head = (q.head + 1) % MAX_ACTIVITY_SIZE;
		q.n--;
		sem_post(cur);
	}
}

int main(int argc, char** argv){
    //Initialize queue, master semaphore
    struct activity_queue q;
    q.head = 0;
    q.tail = 0;
    q.n = 0;
    sem_t* master_sem = malloc(sizeof(sem_t));
    sem_init(master_sem, 0, 1);
    master_thread_func(master_sem, q);
}
